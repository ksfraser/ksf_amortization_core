<?php
/**
 * Journal Entry Builder for FrontAccounting GL
 *
 * Constructs properly-formatted journal entries for GL posting, ensuring
 * balanced debit/credit entries and proper reference tracking.
 *
 * ### UML Class Diagram
 * ```
 * ┌──────────────────────────────────────────┐
 * │     JournalEntryBuilder                  │
 * ├──────────────────────────────────────────┤
 * │ - debits: array                          │
 * │ - credits: array                         │
 * │ - reference: string                      │
 * │ - memo: string                           │
 * │ - postDate: DateTime                     │
 * ├──────────────────────────────────────────┤
 * │ + addDebit(string, float, string): self  │
 * │ + addCredit(string, float, string): self │
 * │ + setReference(string): self             │
 * │ + setMemo(string): self                  │
 * │ + setDate(DateTime): self                │
 * │ + build(): array                         │
 * │ + isBalanced(): bool                     │
 * │ - roundAmount(float): float              │
 * └──────────────────────────────────────────┘
 * ```
 *
 * ### Journal Entry Example
 * ```php
 * $builder = new JournalEntryBuilder();
 * $entry = $builder
 *     ->setDate(new DateTime('2025-01-15'))
 *     ->setReference('LOAN-123-2025-01-15')
 *     ->setMemo('Loan Payment - Principal $600, Interest $400')
 *     ->addDebit('2100', 600.00, 'Loan principal payment')
 *     ->addDebit('6200', 400.00, 'Interest expense')
 *     ->addCredit('1100', 1000.00, 'Payment received')
 *     ->build();
 * ```
 *
 * ### Design Principles
 * - **S**ingle Responsibility: Only constructs journal entries
 * - **B**uilder Pattern: Fluent interface for entry construction
 * - **V**alidation: Ensures balanced entries before returning
 *
 * @package   Ksfraser\Amortizations\FA
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-08
 */

namespace Ksfraser\Amortizations\FA;

use DateTime;
use RuntimeException;

/**
 * Builder for constructing balanced journal entries
 */
class JournalEntryBuilder
{
    /**
     * @var array Debit entries [['account' => code, 'amount' => value, 'memo' => text], ...]
     */
    private array $debits = [];

    /**
     * @var array Credit entries [['account' => code, 'amount' => value, 'memo' => text], ...]
     */
    private array $credits = [];

    /**
     * @var string Reference for GL entry (e.g., "LOAN-123-2025-01-15")
     */
    private string $reference = '';

    /**
     * @var string Description/memo for the journal entry
     */
    private string $memo = '';

    /**
     * @var DateTime Date for the journal entry
     */
    private DateTime $postDate;

    /**
     * Amount rounding precision (FA uses 4 decimal places)
     *
     * @var int
     */
    private const DECIMAL_PLACES = 4;

    /**
     * Constructor
     *
     * Initializes builder with current date
     */
    public function __construct()
    {
        $this->postDate = new DateTime();
    }

    /**
     * Add a debit entry
     *
     * ### Purpose
     * Records a debit (increase) to an account
     *
     * ### Debit Conventions
     * - Asset accounts: increase with debit
     * - Liability accounts: decrease with debit
     * - Expense accounts: increase with debit
     *
     * @param string $accountCode GL account code (e.g., "2100")
     * @param float $amount Amount to debit (must be positive)
     * @param string $memo Description for this debit line
     *
     * @return $this Fluent interface for chaining
     *
     * @throws RuntimeException If amount is negative
     */
    public function addDebit(string $accountCode, float $amount, string $memo = ''): self
    {
        if ($amount < 0) {
            throw new RuntimeException('Debit amount must be positive');
        }

        $this->debits[] = [
            'account' => $accountCode,
            'amount' => $this->roundAmount($amount),
            'memo' => $memo,
        ];

        return $this;
    }

    /**
     * Add a credit entry
     *
     * ### Purpose
     * Records a credit (decrease from debit perspective) to an account
     *
     * ### Credit Conventions
     * - Asset accounts: decrease with credit
     * - Liability accounts: increase with credit
     * - Revenue accounts: increase with credit
     *
     * @param string $accountCode GL account code (e.g., "1100")
     * @param float $amount Amount to credit (must be positive)
     * @param string $memo Description for this credit line
     *
     * @return $this Fluent interface for chaining
     *
     * @throws RuntimeException If amount is negative
     */
    public function addCredit(string $accountCode, float $amount, string $memo = ''): self
    {
        if ($amount < 0) {
            throw new RuntimeException('Credit amount must be positive');
        }

        $this->credits[] = [
            'account' => $accountCode,
            'amount' => $this->roundAmount($amount),
            'memo' => $memo,
        ];

        return $this;
    }

    /**
     * Set journal entry reference
     *
     * ### Purpose
     * Stores reference to the loan for tracking and reconciliation
     *
     * ### Format
     * "LOAN-{loanId}-{paymentDate}"
     * Example: "LOAN-123-2025-01-15"
     *
     * @param string $reference Reference identifier
     *
     * @return $this Fluent interface for chaining
     */
    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * Set journal entry memo/description
     *
     * ### Purpose
     * Documents the nature of the journal entry
     *
     * ### Example
     * "Loan Payment - Principal $600, Interest $400"
     *
     * @param string $memo Description text (max 255 characters in FA)
     *
     * @return $this Fluent interface for chaining
     */
    public function setMemo(string $memo): self
    {
        $this->memo = substr($memo, 0, 255); // FA memo field limit
        return $this;
    }

    /**
     * Set journal entry posting date
     *
     * ### Purpose
     * Determines the fiscal period for the entry
     *
     * @param DateTime $date Date to post entry
     *
     * @return $this Fluent interface for chaining
     */
    public function setDate(DateTime $date): self
    {
        $this->postDate = $date;
        return $this;
    }

    /**
     * Build and return the journal entry
     *
     * ### Process
     * 1. Validate entry is balanced (debits = credits)
     * 2. Return properly formatted entry array for FA GL posting
     *
     * ### Return Format
     * ```php
     * [
     *     'post_date' => '2025-01-15',
     *     'reference' => 'LOAN-123-2025-01-15',
     *     'memo' => 'Loan Payment - Principal $600, Interest $400',
     *     'debits' => [
     *         ['account' => '2100', 'amount' => 600.00, 'memo' => '...'],
     *         ['account' => '6200', 'amount' => 400.00, 'memo' => '...'],
     *     ],
     *     'credits' => [
     *         ['account' => '1100', 'amount' => 1000.00, 'memo' => '...'],
     *     ],
     *     'is_balanced' => true,
     *     'total_amount' => 1000.00
     * ]
     * ```
     *
     * @return array Journal entry ready for FA GL posting
     *
     * @throws RuntimeException If entry is not balanced
     */
    public function build(): array
    {
        if (!$this->isBalanced()) {
            $debitTotal = $this->getDebitTotal();
            $creditTotal = $this->getCreditTotal();
            throw new RuntimeException(
                "Journal entry is not balanced. Debits: {$debitTotal}, Credits: {$creditTotal}"
            );
        }

        return [
            'post_date' => $this->postDate->format('Y-m-d'),
            'reference' => $this->reference,
            'memo' => $this->memo,
            'debits' => $this->debits,
            'credits' => $this->credits,
            'is_balanced' => true,
            'total_amount' => $this->getDebitTotal(),
        ];
    }

    /**
     * Check if journal entry is balanced
     *
     * ### Rule
     * Total debits must equal total credits (within rounding tolerance)
     *
     * @return bool True if balanced, false otherwise
     */
    public function isBalanced(): bool
    {
        $tolerance = 0.01; // Allow for rounding
        $diff = abs($this->getDebitTotal() - $this->getCreditTotal());
        return $diff < $tolerance;
    }

    /**
     * Get total debit amount
     *
     * @return float Sum of all debit amounts
     */
    public function getDebitTotal(): float
    {
        return array_reduce(
            $this->debits,
            fn($carry, $item) => $carry + $item['amount'],
            0.0
        );
    }

    /**
     * Get total credit amount
     *
     * @return float Sum of all credit amounts
     */
    public function getCreditTotal(): float
    {
        return array_reduce(
            $this->credits,
            fn($carry, $item) => $carry + $item['amount'],
            0.0
        );
    }

    /**
     * Round amount to FA precision
     *
     * ### Precision
     * FrontAccounting uses 4 decimal places for amounts
     *
     * @param float $amount Amount to round
     *
     * @return float Rounded amount
     */
    private function roundAmount(float $amount): float
    {
        return round($amount, self::DECIMAL_PLACES);
    }

    /**
     * Reset builder for new entry
     *
     * ### Purpose
     * Clear all entries to build a new journal entry
     *
     * @return $this Fluent interface for chaining
     */
    public function reset(): self
    {
        $this->debits = [];
        $this->credits = [];
        $this->reference = '';
        $this->memo = '';
        $this->postDate = new DateTime();
        return $this;
    }

    /**
     * Get entry summary
     *
     * ### Purpose
     * Display summary of entry before posting
     *
     * @return string Human-readable summary
     */
    public function getSummary(): string
    {
        return sprintf(
            "Journal Entry: %s\n  %d debits totaling %.2f\n  %d credits totaling %.2f\n  Balanced: %s",
            $this->reference,
            count($this->debits),
            $this->getDebitTotal(),
            count($this->credits),
            $this->getCreditTotal(),
            $this->isBalanced() ? 'Yes' : 'No'
        );
    }
}

?>
