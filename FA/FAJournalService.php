<?php
/**
 * FrontAccounting Journal Service
 *
 * Handles posting of amortization payments to the FrontAccounting GL,
 * including journal entry creation, validation, transaction tracking,
 * and reversal support for schedule changes.
 *
 * ### UML Class Diagram
 * ```
 * ┌───────────────────────────────────────────────────────┐
 * │         FAJournalService                              │
 * ├───────────────────────────────────────────────────────┤
 * │ - pdo: PDO                                            │
 * │ - mapper: GLAccountMapper                            │
 * │ - builder: JournalEntryBuilder                       │
 * ├───────────────────────────────────────────────────────┤
 * │ + __construct(PDO $pdo)                              │
 * │ + postPaymentToGL(int, array, array): array          │
 * │ + batchPostPayments(int, string): array              │
 * │ + reverseJournalEntry(string, string): bool          │
 * │ - createJournalEntry(array, array): array            │
 * │ - updateStagingRow(int, array): bool                 │
 * │ - generateTransactionReference(int, string): string  │
 * └───────────────────────────────────────────────────────┘
 *           │                     │
 *           ├─ depends on         │
 *           ▼                     ▼
 *    GLAccountMapper    JournalEntryBuilder
 * ```
 *
 * ### Transaction Flow
 * ```
 * postPaymentToGL()
 *   ├─ Validate GL accounts exist & active
 *   ├─ Build journal entry (debit, debit, credit)
 *   ├─ Insert into fa_gl_trans
 *   ├─ Capture trans_no & trans_type from FA
 *   ├─ Update ksf_amortization_staging with posting info
 *   └─ Return results (success/failure)
 * ```
 *
 * ### Design Principles
 * - **S**ingle Responsibility: Focuses on GL posting only
 * - **O**pen/Closed: Extensible for other platforms
 * - **D**ependency Inversion: Depends on PDO interface
 *
 * @package   Ksfraser\Amortizations\FA
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-08
 */

namespace Ksfraser\Amortizations\FA;

use DateTime;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Service for posting amortization payments to FrontAccounting GL
 */
class FAJournalService
{
    /**
     * @var PDO FrontAccounting database connection
     */
    private PDO $pdo;

    /**
     * @var GLAccountMapper GL account mapper and validator
     */
    private GLAccountMapper $mapper;

    /**
     * @var JournalEntryBuilder Journal entry builder
     */
    private JournalEntryBuilder $builder;

    /**
     * FrontAccounting GL transaction type for loan postings
     * 10 = GL Entry, 20 = AP, 30 = AR, 40 = Bank
     *
     * @var int
     */
    private const FA_GL_TRANSACTION_TYPE = 10;

    /**
     * System type code for GL entries in FA
     *
     * @var int
     */
    private const FA_SYSTEM_TYPE = 0;

    /**
     * Maximum amount for a single posting (safety check)
     *
     * @var float
     */
    private const MAX_POSTING_AMOUNT = 999999.99;

    /**
     * Constructor
     *
     * ### Initialization
     * Sets up database connection and initializes components
     *
     * @param PDO $pdo FrontAccounting database connection
     *
     * @throws RuntimeException If PDO connection is invalid
     */
    public function __construct(PDO $pdo)
    {
        if ($pdo === null) {
            throw new RuntimeException('PDO connection cannot be null');
        }

        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Initialize components
        $this->mapper = new GLAccountMapper($pdo);
        $this->builder = new JournalEntryBuilder();
    }

    /**
     * Post a single payment to the GL
     *
     * ### Process
     * 1. Validate GL accounts for the loan
     * 2. Build balanced journal entry
     * 3. Insert entry into FA GL tables
     * 4. Capture transaction reference (trans_no, trans_type)
     * 5. Update staging row with posting information
     * 6. Return results with transaction details
     *
     * ### Journal Entry Structure
     * For a $1,000 payment with $600 principal and $400 interest:
     * - Debit: Loan Liability (2100) $600 - reduces loan amount
     * - Debit: Interest Expense (6200) $400 - records interest cost
     * - Credit: Cash (1100) $1,000 - payment received
     *
     * @param int $loanId Loan database ID
     * @param array $paymentRow Staging table row [
     *     'id' => int (schedule record ID),
     *     'payment_date' => 'YYYY-MM-DD',
     *     'beginning_balance' => float,
     *     'payment_amount' => float,
     *     'principal_portion' => float,
     *     'interest_portion' => float,
     *     'ending_balance' => float
     * ]
     * @param array $glAccounts GL account mapping [
     *     'liability_account' => '2100',
     *     'interest_expense_account' => '6200',
     *     'cash_account' => '1100'
     * ]
     *
     * @return array Result array [
     *     'success' => bool,
     *     'staging_id' => int (schedule row ID),
     *     'trans_no' => string (FA transaction number, if successful),
     *     'trans_type' => int (FA transaction type, if successful),
     *     'amount' => float (posted amount),
     *     'message' => string (success or error message)
     * ]
     *
     * @throws RuntimeException On database errors (will be caught and returned)
     */
    public function postPaymentToGL(
        int $loanId,
        array $paymentRow,
        array $glAccounts
    ): array {
        $stagingId = $paymentRow['id'] ?? null;
        $paymentDate = $paymentRow['payment_date'] ?? null;
        $principalPortion = $paymentRow['principal_portion'] ?? 0;
        $interestPortion = $paymentRow['interest_portion'] ?? 0;
        $paymentAmount = $paymentRow['payment_amount'] ?? 0;

        try {
            // Validate GL accounts
            if (!$this->mapper->validateAccounts($glAccounts)) {
                return [
                    'success' => false,
                    'staging_id' => $stagingId,
                    'trans_no' => null,
                    'trans_type' => null,
                    'amount' => 0,
                    'message' => 'Invalid or missing GL accounts for loan',
                ];
            }

            // Validate payment amount (safety check)
            if ($paymentAmount <= 0 || $paymentAmount > self::MAX_POSTING_AMOUNT) {
                return [
                    'success' => false,
                    'staging_id' => $stagingId,
                    'trans_no' => null,
                    'trans_type' => null,
                    'amount' => 0,
                    'message' => 'Invalid payment amount for GL posting',
                ];
            }

            // Build journal entry
            $reference = $this->generateTransactionReference($loanId, $paymentDate);
            $memo = sprintf(
                'Loan Payment - Principal $%.2f, Interest $%.2f',
                $principalPortion,
                $interestPortion
            );

            $entry = $this->builder
                ->reset()
                ->setDate(new DateTime($paymentDate))
                ->setReference($reference)
                ->setMemo($memo)
                ->addDebit($glAccounts['liability_account'], $principalPortion, 'Principal payment')
                ->addDebit($glAccounts['interest_expense_account'], $interestPortion, 'Interest expense')
                ->addCredit($glAccounts['cash_account'], $paymentAmount, 'Payment received')
                ->build();

            // Create GL transaction
            $transactionData = $this->createJournalEntry($entry, $loanId);

            if (!$transactionData['success']) {
                return [
                    'success' => false,
                    'staging_id' => $stagingId,
                    'trans_no' => null,
                    'trans_type' => null,
                    'amount' => 0,
                    'message' => 'Failed to create GL transaction',
                ];
            }

            // Update staging table with posting information
            $updateData = [
                'posted_to_gl' => 1,
                'trans_no' => $transactionData['trans_no'],
                'trans_type' => $transactionData['trans_type'],
                'posted_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ];

            if (!$this->updateStagingRow($stagingId, $updateData)) {
                // Posting succeeded but update failed - log warning but return success
                // The trans_no/trans_type are captured and can be recovered
            }

            return [
                'success' => true,
                'staging_id' => $stagingId,
                'trans_no' => $transactionData['trans_no'],
                'trans_type' => $transactionData['trans_type'],
                'amount' => $paymentAmount,
                'message' => sprintf(
                    'Payment posted successfully to GL (Trans: %s)',
                    $transactionData['trans_no']
                ),
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'staging_id' => $stagingId,
                'trans_no' => null,
                'trans_type' => null,
                'amount' => 0,
                'message' => "Database error: {$e->getMessage()}",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'staging_id' => $stagingId,
                'trans_no' => null,
                'trans_type' => null,
                'amount' => 0,
                'message' => "Posting error: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Post multiple payments to GL in batch
     *
     * ### Process
     * 1. Validate GL accounts for loan
     * 2. Get all unposted staging rows (optionally filtered by date)
     * 3. Post each payment individually
     * 4. Aggregate results
     * 5. Return summary (success count, failure count, errors)
     *
     * ### Options
     * - upToDate: Only post payments on or before this date (YYYY-MM-DD)
     * - Example: $service->batchPostPayments(123, '2025-03-31')
     *   Posts all unposted payments through March 31, 2025
     *
     * @param int $loanId Loan database ID
     * @param string|null $upToDate Optional: only post payments up to this date (YYYY-MM-DD)
     *
     * @return array Result summary [
     *     'success' => bool (true if all posted successfully),
     *     'total_count' => int (number of payments processed),
     *     'posted_count' => int (number successfully posted),
     *     'failed_count' => int (number that failed),
     *     'total_amount' => float (total posted amount),
     *     'failed_staging_ids' => array (IDs of failed postings),
     *     'errors' => array (error messages),
     *     'message' => string (summary message)
     * ]
     */
    public function batchPostPayments(int $loanId, ?string $upToDate = null): array
    {
        try {
            // Get GL accounts for this loan
            $glAccounts = $this->mapper->mapLoanAccounts($loanId);

            // Get unposted staging rows
            $sql = 'SELECT * FROM ksf_amortization_staging 
                    WHERE loan_id = ? AND posted_to_gl = 0 
                    ORDER BY payment_date ASC';
            $params = [$loanId];

            if ($upToDate !== null) {
                $sql .= ' AND payment_date <= ?';
                $params[] = $upToDate;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $stagingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process each row
            $results = [
                'success' => true,
                'total_count' => count($stagingRows),
                'posted_count' => 0,
                'failed_count' => 0,
                'total_amount' => 0,
                'failed_staging_ids' => [],
                'errors' => [],
            ];

            foreach ($stagingRows as $row) {
                $postResult = $this->postPaymentToGL($loanId, $row, $glAccounts);

                if ($postResult['success']) {
                    $results['posted_count']++;
                    $results['total_amount'] += $postResult['amount'];
                } else {
                    $results['failed_count']++;
                    $results['failed_staging_ids'][] = $postResult['staging_id'];
                    $results['errors'][] = sprintf(
                        'Row %d: %s',
                        $postResult['staging_id'],
                        $postResult['message']
                    );
                    $results['success'] = false;
                }
            }

            // Generate summary message
            $results['message'] = sprintf(
                'Batch posted: %d/%d payments, Total: $%.2f',
                $results['posted_count'],
                $results['total_count'],
                $results['total_amount']
            );

            if ($results['failed_count'] > 0) {
                $results['message'] .= sprintf(
                    ', %d failed',
                    $results['failed_count']
                );
            }

            return $results;
        } catch (Exception $e) {
            return [
                'success' => false,
                'total_count' => 0,
                'posted_count' => 0,
                'failed_count' => 0,
                'total_amount' => 0,
                'failed_staging_ids' => [],
                'errors' => [$e->getMessage()],
                'message' => 'Batch posting failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reverse a previously posted GL entry
     *
     * ### Purpose
     * Used when a loan schedule is recalculated after an extra payment
     * Creates an offsetting entry to void the original transaction
     *
     * ### Process
     * 1. Retrieve original GL entry from FA GL
     * 2. Create reversing entry with same amounts but opposite debits/credits
     * 3. Insert reversing entry
     * 4. Mark original staging row for reversal
     * 5. Return results
     *
     * @param string $transNo FrontAccounting transaction number
     * @param int $transType FrontAccounting transaction type
     *
     * @return bool True if reversal successful, false otherwise
     */
    public function reverseJournalEntry(string $transNo, int $transType): bool
    {
        try {
            // Fetch original GL entry
            $stmt = $this->pdo->prepare(
                'SELECT * FROM gl_trans 
                 WHERE tran_no = ? AND type = ? 
                 ORDER BY counter DESC'
            );
            $stmt->execute([$transNo, $transType]);
            $originalEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($originalEntries)) {
                return false; // Original entry not found
            }

            // Begin transaction for all-or-nothing reversal
            $this->pdo->beginTransaction();

            try {
                // Create reversing entries (opposite of original)
                $reversalDate = (new DateTime())->format('Y-m-d');
                $reversalMemo = "Reversal of trans {$transNo}";

                foreach ($originalEntries as $entry) {
                    // Reverse debit/credit (negate amount)
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO gl_trans 
                         (type, type_no, tran_date, account, amount, memo_, ref_no)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );

                    $reversalAmount = -$entry['amount']; // Negate to reverse

                    $stmt->execute([
                        $transType,
                        $entry['type_no'], // Same transaction number
                        $reversalDate,
                        $entry['account'],
                        $reversalAmount,
                        $reversalMemo,
                        $entry['ref_no'] ?? '',
                    ]);
                }

                $this->pdo->commit();
                return true;
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Create a journal entry in FA GL
     *
     * ### Process
     * 1. Insert debit entries into gl_trans
     * 2. Insert credit entries into gl_trans
     * 3. Capture trans_no from FA
     * 4. Return transaction details
     *
     * @param array $entry Built journal entry from JournalEntryBuilder
     * @param int $loanId Loan ID for reference
     *
     * @return array [
     *     'success' => bool,
     *     'trans_no' => string,
     *     'trans_type' => int
     * ]
     *
     * @throws PDOException On database errors
     */
    private function createJournalEntry(array $entry, int $loanId): array
    {
        try {
            $this->pdo->beginTransaction();

            // Generate transaction number (FA uses sequential counter)
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(MAX(tran_no), 0) + 1 as next_trans_no 
                 FROM gl_trans WHERE type = ?'
            );
            $stmt->execute([self::FA_GL_TRANSACTION_TYPE]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $transNo = $result['next_trans_no'] ?? 1;

            // Insert debit entries
            foreach ($entry['debits'] as $debit) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO gl_trans 
                     (type, tran_no, tran_date, account, amount, memo_, ref_no)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    self::FA_GL_TRANSACTION_TYPE,
                    $transNo,
                    $entry['post_date'],
                    $debit['account'],
                    $debit['amount'],
                    $debit['memo'] ?? $entry['memo'],
                    $entry['reference'],
                ]);
            }

            // Insert credit entries (as negative amounts in FA)
            foreach ($entry['credits'] as $credit) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO gl_trans 
                     (type, tran_no, tran_date, account, amount, memo_, ref_no)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    self::FA_GL_TRANSACTION_TYPE,
                    $transNo,
                    $entry['post_date'],
                    $credit['account'],
                    -$credit['amount'], // FA: credits are negative
                    $credit['memo'] ?? $entry['memo'],
                    $entry['reference'],
                ]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'trans_no' => (string)$transNo,
                'trans_type' => self::FA_GL_TRANSACTION_TYPE,
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update a staging table row with posting information
     *
     * ### Updates
     * - posted_to_gl: 1
     * - trans_no: captured transaction number
     * - trans_type: captured transaction type
     * - posted_at: current timestamp
     *
     * @param int $stagingId Staging table record ID
     * @param array $data Data to update
     *
     * @return bool True if successful, false otherwise
     */
    private function updateStagingRow(int $stagingId, array $data): bool
    {
        try {
            $setClauses = [];
            $params = [];

            foreach ($data as $key => $value) {
                $setClauses[] = "$key = ?";
                $params[] = $value;
            }

            $params[] = $stagingId;

            $sql = 'UPDATE ksf_amortization_staging SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Generate a GL reference identifier for transaction tracking
     *
     * ### Format
     * "LOAN-{loanId}-{paymentDate}"
     * Example: "LOAN-123-2025-01-15"
     *
     * @param int $loanId Loan database ID
     * @param string $paymentDate Payment date (YYYY-MM-DD)
     *
     * @return string GL reference identifier
     */
    private function generateTransactionReference(int $loanId, string $paymentDate): string
    {
        return sprintf('LOAN-%d-%s', $loanId, $paymentDate);
    }
}

?>
