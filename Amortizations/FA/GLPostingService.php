<?php
/**
 * GL Posting Service - Bridges AmortizationModel with FA GL posting
 *
 * Handles automatic GL posting of amortization payments to FrontAccounting.
 * Works with AmortizationModel to post payments as they're created or updated.
 *
 * ### Responsibility (SRP)
 * - Orchestrates GL posting workflow
 * - Manages GL posting configuration per loan
 * - Handles posting failures and retries
 * - Tracks posting status in staging table
 *
 * ### Dependencies (DIP)
 * - FAJournalService: Posts journal entries to GL
 * - GLAccountMapper: Maps GL accounts for loans
 * - DataProviderInterface: Accesses loan/schedule data
 *
 * ### Design Patterns
 * - Service Pattern: Orchestrates GL posting workflow
 * - Strategy Pattern: Supports different posting strategies
 * - Dependency Injection: All dependencies injected
 *
 * ### Usage Example
 * ```php
 * $service = new GLPostingService($pdo, $dataProvider);
 *
 * // Post a single payment
 * $result = $service->postPaymentSchedule(
 *     loanId: 123,
 *     paymentNumber: 1,
 *     glAccounts: ['liability_account' => '2100', ...]
 * );
 *
 * // Batch post all unposted payments
 * $results = $service->batchPostLoanPayments(123);
 *
 * // Reverse previous postings (for schedule recalculation)
 * $service->reverseSchedulePostings(123, fromDate: '2025-01-15');
 * ```
 *
 * @package   Ksfraser\Amortizations\FA
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-08
 */

namespace Ksfraser\Amortizations\FA;

use PDO;
use Ksfraser\Amortizations\DataProviderInterface;
use RuntimeException;
use DateTime;

/**
 * GL Posting Service
 *
 * Orchestrates the posting of amortization payments to FrontAccounting GL
 */
class GLPostingService
{
    /**
     * @var PDO Database connection
     */
    private PDO $pdo;

    /**
     * @var DataProviderInterface Data access layer
     */
    private DataProviderInterface $dataProvider;

    /**
     * @var FAJournalService Journal entry posting service
     */
    private FAJournalService $journalService;

    /**
     * @var GLAccountMapper GL account mapping service
     */
    private GLAccountMapper $accountMapper;

    /**
     * @var array Configuration for GL posting behavior
     */
    private array $config = [
        'auto_post_enabled' => true,
        'post_on_schedule_generation' => true,
        'post_on_extra_payment' => true,
        'post_on_skip_payment' => false,  // Skip payments typically increase balance, don't post
        'max_retry_attempts' => 3,
        'retry_delay_seconds' => 60,
    ];

    /**
     * Constructor with dependency injection
     *
     * @param PDO $pdo FrontAccounting database connection
     * @param DataProviderInterface $dataProvider Data access layer
     * @param FAJournalService|null $journalService Optional journal service (created if null)
     * @param GLAccountMapper|null $accountMapper Optional account mapper (created if null)
     *
     * @throws RuntimeException If PDO or DataProvider is invalid
     */
    public function __construct(
        PDO $pdo,
        DataProviderInterface $dataProvider,
        ?FAJournalService $journalService = null,
        ?GLAccountMapper $accountMapper = null
    ) {
        if (!$pdo) {
            throw new RuntimeException('PDO connection required');
        }
        if (!$dataProvider) {
            throw new RuntimeException('DataProviderInterface required');
        }

        $this->pdo = $pdo;
        $this->dataProvider = $dataProvider;
        $this->journalService = $journalService ?? new FAJournalService($pdo);
        $this->accountMapper = $accountMapper ?? new GLAccountMapper($pdo);
    }

    /**
     * Post a single payment to GL
     *
     * ### Purpose
     * Posts a specific schedule payment row to the FrontAccounting GL.
     * Called for individual payments that need GL posting.
     *
     * ### Process
     * 1. Retrieve schedule row from database
     * 2. Get GL account configuration for loan
     * 3. Create balanced journal entry (debit liability/interest, credit cash)
     * 4. Post to FA GL tables
     * 5. Update staging table with posting info
     *
     * ### Returns
     * Array with keys:
     * - success: bool - Whether posting succeeded
     * - payment_number: int - Payment sequence number
     * - trans_no: string|null - FA transaction number if posted
     * - trans_type: int|null - FA transaction type if posted
     * - amount: float - Payment amount
     * - error: string|null - Error message if failed
     * - timestamp: string - ISO 8601 timestamp
     *
     * @param int $loanId Loan database ID
     * @param int $paymentNumber Payment sequence number (1-based)
     * @param array $glAccounts Optional GL account overrides
     *
     * @return array Posting result
     * @throws RuntimeException If loan not found
     */
    public function postPaymentSchedule(
        int $loanId,
        int $paymentNumber,
        array $glAccounts = []
    ): array {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');

        try {
            // Get loan for validation
            $loan = $this->dataProvider->getLoan($loanId);
            if (!$loan) {
                throw new RuntimeException("Loan $loanId not found");
            }

            // Get specific schedule row
            $scheduleRow = $this->getScheduleRowByNumber($loanId, $paymentNumber);
            if (!$scheduleRow) {
                return [
                    'success' => false,
                    'payment_number' => $paymentNumber,
                    'trans_no' => null,
                    'trans_type' => null,
                    'amount' => 0,
                    'error' => "Schedule row $paymentNumber not found for loan $loanId",
                    'timestamp' => $timestamp,
                ];
            }

            // Skip if already posted
            if ($scheduleRow['posted_to_gl'] ?? false) {
                return [
                    'success' => false,
                    'payment_number' => $paymentNumber,
                    'trans_no' => $scheduleRow['trans_no'] ?? null,
                    'trans_type' => $scheduleRow['trans_type'] ?? null,
                    'amount' => $scheduleRow['payment_amount'] ?? 0,
                    'error' => "Payment $paymentNumber already posted to GL",
                    'timestamp' => $timestamp,
                ];
            }

            // Get GL accounts (use provided or defaults)
            $accounts = !empty($glAccounts) ? $glAccounts : $this->getDefaultGLAccounts($loanId);

            // Prepare payment row for journal service
            $paymentData = [
                'id' => $scheduleRow['id'] ?? $paymentNumber,
                'payment_date' => $scheduleRow['payment_date'],
                'principal_portion' => $scheduleRow['principal_payment'] ?? 0,
                'interest_portion' => $scheduleRow['interest_payment'] ?? 0,
                'payment_amount' => $scheduleRow['payment_amount'] ?? 0,
            ];

            // Post to GL
            $result = $this->journalService->postPaymentToGL($loanId, $paymentData, $accounts);

            // Add timestamp to result
            $result['timestamp'] = $timestamp;

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'payment_number' => $paymentNumber,
                'trans_no' => null,
                'trans_type' => null,
                'amount' => 0,
                'error' => $e->getMessage(),
                'timestamp' => $timestamp,
            ];
        }
    }

    /**
     * Batch post all unposted payments for a loan
     *
     * ### Purpose
     * Posts all unposted schedule rows for a loan in a single operation.
     * Used when initially setting up GL posting for existing schedules.
     *
     * ### Process
     * 1. Get all unposted schedule rows
     * 2. Post each to GL using journal service
     * 3. Aggregate results (counts, amounts, errors)
     * 4. Return summary
     *
     * ### Returns
     * Array with keys:
     * - success: bool - Whether all postings succeeded
     * - total_count: int - Total payments posted
     * - success_count: int - Number of successful postings
     * - failure_count: int - Number of failed postings
     * - total_amount: float - Sum of all posted amounts
     * - errors: string[] - List of error messages
     * - timestamp: string - ISO 8601 timestamp
     * - details: array[] - Individual posting results
     *
     * @param int $loanId Loan database ID
     * @param string|null $upToDate Optional date filter (YYYY-MM-DD)
     * @param array $glAccounts Optional GL account overrides
     *
     * @return array Batch posting results
     */
    public function batchPostLoanPayments(
        int $loanId,
        ?string $upToDate = null,
        array $glAccounts = []
    ): array {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        $details = [];
        $errors = [];
        $totalAmount = 0;
        $successCount = 0;
        $failureCount = 0;

        try {
            // Get loan for validation
            $loan = $this->dataProvider->getLoan($loanId);
            if (!$loan) {
                throw new RuntimeException("Loan $loanId not found");
            }

            // Get GL accounts
            $accounts = !empty($glAccounts) ? $glAccounts : $this->getDefaultGLAccounts($loanId);

            // Get all unposted schedule rows
            $scheduleRows = $this->getUnpostedScheduleRows($loanId, $upToDate);

            // Post each payment
            foreach ($scheduleRows as $row) {
                $result = $this->journalService->postPaymentToGL($loanId, [
                    'id' => $row['id'],
                    'payment_date' => $row['payment_date'],
                    'principal_portion' => $row['principal_payment'],
                    'interest_portion' => $row['interest_payment'],
                    'payment_amount' => $row['payment_amount'],
                ], $accounts);

                $details[] = $result;

                if ($result['success']) {
                    $successCount++;
                    $totalAmount += $row['payment_amount'];
                } else {
                    $failureCount++;
                    $errors[] = $result['error'] ?? 'Unknown error';
                }
            }

            return [
                'success' => $failureCount === 0,
                'total_count' => count($scheduleRows),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'total_amount' => round($totalAmount, 2),
                'errors' => $errors,
                'timestamp' => $timestamp,
                'details' => $details,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'total_count' => 0,
                'success_count' => 0,
                'failure_count' => 1,
                'total_amount' => 0,
                'errors' => [$e->getMessage()],
                'timestamp' => $timestamp,
                'details' => [],
            ];
        }
    }

    /**
     * Reverse GL postings for schedule recalculation
     *
     * ### Purpose
     * Reverses all GL postings after a given date to prepare for schedule recalculation.
     * Used when extra payments or other events change the schedule.
     *
     * ### Process
     * 1. Get all posted payments on or after event_date
     * 2. Reverse each posting (create offsetting entries)
     * 3. Mark staging rows as unposted
     * 4. Return summary
     *
     * ### Returns
     * Array with keys:
     * - success: bool - Whether all reversals succeeded
     * - reversed_count: int - Number of reversals
     * - failed_count: int - Number of reversal failures
     * - errors: string[] - Error messages
     * - timestamp: string - ISO 8601 timestamp
     *
     * @param int $loanId Loan database ID
     * @param string $fromDate Date to start reversals (YYYY-MM-DD)
     *
     * @return array Reversal results
     */
    public function reverseSchedulePostings(int $loanId, string $fromDate): array
    {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        $errors = [];
        $reversedCount = 0;
        $failedCount = 0;

        try {
            // Get all posted payments on or after fromDate
            $postedRows = $this->getPostedScheduleRowsAfterDate($loanId, $fromDate);

            // Reverse each
            foreach ($postedRows as $row) {
                if (!isset($row['trans_no'], $row['trans_type'])) {
                    $failedCount++;
                    $errors[] = "Payment {$row['payment_number']} missing transaction reference";
                    continue;
                }

                try {
                    $success = $this->journalService->reverseJournalEntry(
                        $row['trans_no'],
                        $row['trans_type']
                    );

                    if ($success) {
                        // Mark as unposted in staging
                        $this->markScheduleRowUnposted($row['id']);
                        $reversedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = "Failed to reverse payment {$row['payment_number']}";
                    }
                } catch (Exception $e) {
                    $failedCount++;
                    $errors[] = "Error reversing {$row['payment_number']}: {$e->getMessage()}";
                }
            }

            return [
                'success' => $failedCount === 0,
                'reversed_count' => $reversedCount,
                'failed_count' => $failedCount,
                'errors' => $errors,
                'timestamp' => $timestamp,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'reversed_count' => 0,
                'failed_count' => 1,
                'errors' => [$e->getMessage()],
                'timestamp' => $timestamp,
            ];
        }
    }

    /**
     * Set GL posting configuration
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     *
     * @return self For method chaining
     */
    public function setConfig(string $key, $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Get GL posting configuration
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     *
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    // ========================================
    // Private Helper Methods
    // ========================================

    /**
     * Get default GL accounts for a loan
     *
     * @param int $loanId Loan database ID
     *
     * @return array GL account mapping
     * @throws RuntimeException If accounts not configured
     */
    private function getDefaultGLAccounts(int $loanId): array
    {
        $accounts = $this->accountMapper->mapLoanAccounts($loanId);

        if (empty($accounts)) {
            throw new RuntimeException("No GL accounts configured for loan $loanId");
        }

        return $accounts;
    }

    /**
     * Get a specific schedule row by payment number
     *
     * @param int $loanId Loan database ID
     * @param int $paymentNumber Payment sequence number
     *
     * @return array|null Schedule row or null if not found
     */
    private function getScheduleRowByNumber(int $loanId, int $paymentNumber): ?array
    {
        $sql = "
            SELECT * FROM ksf_amortization_schedule
            WHERE loan_id = ? AND payment_number = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$loanId, $paymentNumber]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all unposted schedule rows for a loan
     *
     * @param int $loanId Loan database ID
     * @param string|null $upToDate Optional date filter
     *
     * @return array List of unposted schedule rows
     */
    private function getUnpostedScheduleRows(int $loanId, ?string $upToDate = null): array
    {
        $sql = "
            SELECT * FROM ksf_amortization_schedule
            WHERE loan_id = ? AND (posted_to_gl IS NULL OR posted_to_gl = 0)
        ";

        $params = [$loanId];

        if ($upToDate) {
            $sql .= " AND payment_date <= ?";
            $params[] = $upToDate;
        }

        $sql .= " ORDER BY payment_number ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all posted schedule rows after a date
     *
     * @param int $loanId Loan database ID
     * @param string $fromDate Start date (YYYY-MM-DD)
     *
     * @return array List of posted schedule rows
     */
    private function getPostedScheduleRowsAfterDate(int $loanId, string $fromDate): array
    {
        $sql = "
            SELECT * FROM ksf_amortization_schedule
            WHERE loan_id = ? AND payment_date >= ? AND posted_to_gl = 1
            ORDER BY payment_number ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$loanId, $fromDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Mark a schedule row as unposted
     *
     * @param int $rowId Schedule row database ID
     *
     * @return bool Success
     */
    private function markScheduleRowUnposted(int $rowId): bool
    {
        $sql = "
            UPDATE ksf_amortization_schedule
            SET posted_to_gl = 0, trans_no = NULL, trans_type = NULL
            WHERE id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$rowId]);
    }
}
