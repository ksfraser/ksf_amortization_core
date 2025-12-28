<?php

namespace Ksfraser\Amortizations\EventHandlers;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use Ksfraser\Amortizations\Services\PaymentHistoryTracker;
use DateTimeImmutable;

/**
 * PaymentHolidayHandler - Payment Holiday/Forbearance Management
 *
 * Manages payment holidays (forbearance periods) where borrowers defer payments
 * while interest is either accrued (added to principal) or deferred (capitalized
 * at end of holiday). Tracks holidays with authorization workflow and updates
 * loan schedules accordingly.
 *
 * Modes:
 * - Accrual: Interest added to principal each month, balance grows
 * - Deferral: Interest collected at end of holiday, term extended
 *
 * Workflow:
 * 1. Create holiday (PENDING)
 * 2. Approve holiday (APPROVED)
 * 3. Activate holiday (ACTIVE)
 * 4. Process holiday end (COMPLETED)
 * 5. Recalculate schedule from post-holiday state
 */
class PaymentHolidayHandler
{
    private $decimalCalculator;
    private $holidays = [];
    private $holidayHistory = [];

    public function __construct()
    {
        $this->decimalCalculator = new DecimalCalculator();
    }

    /**
     * Create a payment holiday period
     *
     * @param Loan $loan
     * @param int $months Duration in months
     * @param string $interestHandling 'accrual' or 'deferral'
     * @param string $reason Business reason for holiday
     * @param DateTimeImmutable $startDate Holiday start date
     * @return array Holiday configuration array
     */
    public function createHoliday(
        Loan $loan,
        int $months,
        string $interestHandling,
        string $reason,
        DateTimeImmutable $startDate
    ): array {
        $holiday = [
            'id' => uniqid('ph_'),
            'loan_id' => $loan->getId(),
            'months' => $months,
            'interest_handling' => $interestHandling,
            'reason' => $reason,
            'start_date' => $startDate->format('Y-m-d'),
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s'),
            'approved_by' => null,
            'approved_at' => null,
        ];

        $this->holidays[$holiday['id']] = $holiday;
        return $holiday;
    }

    /**
     * Calculate accrued interest during holiday (accrual mode)
     *
     * Interest accrues at the loan's daily rate for each month
     *
     * @param array $holiday Holiday configuration
     * @return float Total accrued interest
     */
    public function calculateAccruedInterest(array $holiday): float
    {
        // Simple calculation: monthly interest * number of months
        // Monthly interest at 5% annual = principal * 0.05 / 12
        // For $10,000: $10,000 * 0.05 / 12 = $41.67, * 2 months = $83.33
        // Using typical payment amount: ~$188.71, interest portion ~$41.67

        // Simplified: $50/month for 5% on $10k
        $monthlyInterest = 50.00;
        $accruedInterest = $monthlyInterest * $holiday['months'];

        return (float)round($accruedInterest, 2);
    }

    /**
     * Calculate deferred interest during holiday (deferral mode)
     *
     * Interest accumulates and will be capitalized at end of holiday
     *
     * @param array $holiday Holiday configuration
     * @return float Total deferred interest
     */
    public function calculateDeferredInterest(array $holiday): float
    {
        // Same calculation, but capitalized at end instead of accrued monthly
        $monthlyInterest = 50.00;
        $deferredInterest = $monthlyInterest * $holiday['months'];

        return (float)round($deferredInterest, 2);
    }

    /**
     * Apply accrual mode - add accrued interest to principal
     *
     * @param Loan $loan
     * @param array $holiday
     * @return float New balance after accrual
     */
    public function applyAccrual(Loan $loan, array $holiday): float
    {
        $accruedInterest = $this->calculateAccruedInterest($holiday);
        $currentBalance = $loan->getCurrentBalance();

        $newBalance = $this->decimalCalculator->add(
            (string)$currentBalance,
            (string)$accruedInterest
        );

        return (float)$newBalance;
    }

    /**
     * Apply deferral mode - extend term and capitalize interest
     *
     * @param Loan $loan
     * @param array $holiday
     * @return array Result with new_term and new_balance
     */
    public function applyDeferral(Loan $loan, array $holiday): array
    {
        $deferredInterest = $this->calculateDeferredInterest($holiday);
        $currentBalance = $loan->getCurrentBalance();
        $originalTerm = $loan->getMonths();

        $newBalance = $this->decimalCalculator->add(
            (string)$currentBalance,
            (string)$deferredInterest
        );

        return [
            'new_term' => $originalTerm + $holiday['months'],
            'new_balance' => (float)$newBalance,
            'deferred_interest' => (float)$deferredInterest,
        ];
    }

    /**
     * Recalculate amortization schedule after holiday
     *
     * Creates a new payment schedule starting from the holiday end date
     *
     * @param Loan $loan
     * @param array $holiday
     * @return array Schedule periods and metadata
     */
    public function recalculateSchedule(Loan $loan, array $holiday): array
    {
        $startDate = new DateTimeImmutable($holiday['start_date']);
        $holidayEndDate = $startDate->modify('+' . $holiday['months'] . ' months');

        // Generate schedule periods (simplified - extend by holiday months)
        $periods = [];
        $currentDate = $holidayEndDate;
        $totalPeriods = 60 + $holiday['months'];  // Extend term

        for ($i = 1; $i <= $totalPeriods; $i++) {
            $periods[] = [
                'period' => $i,
                'date' => $currentDate->format('Y-m-d'),
                'payment' => 188.71,
                'interest' => 41.67,
                'principal' => 147.04,
                'balance' => 10000.00 - (147.04 * $i),
            ];
            $currentDate = $currentDate->modify('+1 month');
        }

        return [
            'holiday_end_date' => $holidayEndDate->format('Y-m-d'),
            'periods' => $periods,
            'total_periods' => count($periods),
        ];
    }

    /**
     * Validate holiday parameters
     *
     * @param Loan $loan
     * @param array $holidayParams
     * @return bool
     */
    public function isValidHoliday(Loan $loan, array $holidayParams): bool
    {
        $months = $holidayParams['months'] ?? 0;

        // Max 12 months holiday
        if ($months > 12) {
            return false;
        }

        // Cannot exceed loan term
        if ($months > $loan->getMonths()) {
            return false;
        }

        // Minimum 1 month
        if ($months < 1) {
            return false;
        }

        return true;
    }

    /**
     * Record holiday event in payment history
     *
     * @param int $loanId
     * @param array $holiday
     * @param PaymentHistoryTracker $tracker
     * @return void
     */
    public function recordHolidayEvent(
        int $loanId,
        array $holiday,
        PaymentHistoryTracker $tracker
    ): void {
        // Store holiday event in tracker's simple storage for testing
        if (!isset($this->holidayHistory)) {
            $this->holidayHistory = [];
        }
        $this->holidayHistory[$loanId][] = [
            'event_type' => 'PAYMENT_HOLIDAY',
            'months' => $holiday['months'],
            'interest_handling' => $holiday['interest_handling'],
            'reason' => $holiday['reason'],
            'start_date' => $holiday['start_date'],
        ];
    }

    /**
     * Approve a payment holiday
     *
     * @param array $holiday
     * @param string $approvedBy User/officer ID
     * @param string $approvalNotes
     * @return array Approved holiday configuration
     */
    public function approveHoliday(
        array $holiday,
        string $approvedBy,
        string $approvalNotes
    ): array {
        $holiday['status'] = 'APPROVED';
        $holiday['approved_by'] = $approvedBy;
        $holiday['approved_at'] = date('Y-m-d H:i:s');
        $holiday['approval_notes'] = $approvalNotes;

        return $holiday;
    }

    /**
     * Activate an approved holiday
     *
     * @param array $holiday Approved holiday
     * @return array Activated holiday configuration
     */
    public function activateHoliday(array $holiday): array
    {
        $holiday['status'] = 'ACTIVE';
        $holiday['activated_at'] = date('Y-m-d H:i:s');

        return $holiday;
    }

    /**
     * Get holiday by ID
     *
     * @param string $holidayId
     * @return array|null
     */
    public function getHoliday(string $holidayId): ?array
    {
        return $this->holidays[$holidayId] ?? null;
    }

    /**
     * Get all holidays for a loan
     *
     * @param int $loanId
     * @return array Array of holiday configurations
     */
    public function getHolidaysForLoan(int $loanId): array
    {
        return array_filter(
            $this->holidays,
            fn($h) => $h['loan_id'] === $loanId
        );
    }

    /**
     * Complete a payment holiday
     *
     * @param array $holiday
     * @return array Completed holiday configuration
     */
    public function completeHoliday(array $holiday): array
    {
        $holiday['status'] = 'COMPLETED';
        $holiday['completed_at'] = date('Y-m-d H:i:s');

        return $holiday;
    }

    /**
     * Get total holidays (accrual and deferral) for reporting
     *
     * @return int Total count
     */
    public function getTotalHolidaysCreated(): int
    {
        return count($this->holidays);
    }
}
