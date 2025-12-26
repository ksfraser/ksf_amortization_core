<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTimeImmutable;

/**
 * LoanRefinancingService - Loan Refinancing and Modification Service
 *
 * Handles refinancing operations including rate modifications, term extensions,
 * principal reductions, and combinations thereof. Supports full refinance
 * scenarios and calculates interest savings and break-even analysis.
 *
 * Refinancing scenarios:
 * 1. Rate reduction: Lower interest rate, same term
 * 2. Term extension: Same rate, longer term (lower payment)
 * 3. Principal reduction: Pay down balance via lump sum
 * 4. Combinations: Any mix of above
 *
 * Features:
 * - Calculate new payment amounts
 * - Compute interest savings
 * - Generate new amortization schedules
 * - Validate refinance parameters
 * - Calculate break-even analysis
 * - Track refinance approvals and status
 *
 * @package Ksfraser\Amortizations\Services
 */
class LoanRefinancingService
{
    /**
     * Decimal calculator for precision
     * @var DecimalCalculator
     */
    private $calculator;

    /**
     * Maximum allowed loan term (in months)
     */
    private const MAX_TERM_MONTHS = 100;

    /**
     * Minimum allowed interest rate (0.1%)
     */
    private const MIN_RATE = 0.001;

    /**
     * Maximum allowed interest rate (20%)
     */
    private const MAX_RATE = 0.20;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Create a refinance proposal for a loan
     *
     * @param Loan $loan Loan to refinance
     * @param float|null $newRate New annual interest rate (null = keep current)
     * @param int|null $newTerm New term in months (null = keep current)
     * @param float|null $principalReduction Amount to reduce principal (null = none)
     * @param DateTimeImmutable $effectiveDate When refinance becomes effective
     * @return array Refinance proposal
     */
    public function createRefinance(
        Loan $loan,
        $newRate = null,
        $newTerm = null,
        $principalReduction = null,
        DateTimeImmutable $effectiveDate = null
    ) {
        $newRate = $newRate ?? $loan->getAnnualRate();
        $newTerm = $newTerm ?? $loan->getMonths();
        $principalReduction = $principalReduction ?? 0;
        $effectiveDate = $effectiveDate ?? new DateTimeImmutable();

        $currentBalance = $loan->getCurrentBalance();
        $newPrincipal = $currentBalance - $principalReduction;

        return [
            'loan_id' => $loan->getId(),
            'original_rate' => $loan->getAnnualRate(),
            'new_rate' => $newRate,
            'original_term' => $loan->getMonths(),
            'new_term' => $newTerm,
            'remaining_months' => $loan->getMonths(),
            'current_balance' => $currentBalance,
            'principal_reduction' => $principalReduction,
            'new_principal' => $newPrincipal,
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'status' => 'PENDING',
            'approved_by' => null,
            'approved_at' => null,
            'activated_at' => null,
        ];
    }

    /**
     * Calculate new monthly payment after refinancing
     *
     * @param Loan $loan Current loan state
     * @param array $refinance Refinance proposal
     * @return float New monthly payment amount
     */
    public function calculateNewPayment(Loan $loan, array $refinance)
    {
        $principal = $refinance['new_principal'];
        $annualRate = $refinance['new_rate'];
        $months = $refinance['new_term'];

        // Handle zero-rate case
        if ($annualRate <= 0) {
            return round($principal / $months, 2);
        }

        $monthlyRate = $this->calculator->divide($annualRate, 12);

        // PMT formula: P * (r * (1+r)^n) / ((1+r)^n - 1)
        $rPlus1 = $this->calculator->add(1, $monthlyRate);
        $rPlus1Pow = bcpow($rPlus1, $months, 10);

        $numerator = $this->calculator->multiply($principal, $monthlyRate);
        $numerator = $this->calculator->multiply($numerator, $rPlus1Pow);

        $denominator = $this->calculator->subtract($rPlus1Pow, 1);

        $payment = $this->calculator->divide($numerator, $denominator);

        return round($payment, 2);
    }

    /**
     * Calculate total interest savings from refinancing
     *
     * @param Loan $loan Current loan
     * @param array $refinance Refinance proposal
     * @return float Total interest savings
     */
    public function calculateInterestSavings(Loan $loan, array $refinance)
    {
        // Calculate old payment and total interest
        $oldRefinance = [
            'new_principal' => $refinance['current_balance'],
            'new_rate' => $refinance['original_rate'],
            'new_term' => $refinance['remaining_months'],
        ];
        $oldPayment = $this->calculateNewPayment($loan, $oldRefinance);
        $oldMonths = $refinance['remaining_months'];
        $oldTotalPayments = $this->calculator->multiply($oldPayment, $oldMonths);
        $oldInterest = $this->calculator->subtract($oldTotalPayments, $refinance['current_balance']);

        // Calculate new payment and total interest
        $newPayment = $this->calculateNewPayment($loan, $refinance);
        $newMonths = $refinance['new_term'];
        $newTotalPayments = $this->calculator->multiply($newPayment, $newMonths);
        $newInterest = $this->calculator->subtract($newTotalPayments, $refinance['new_principal']);

        // Savings is positive when new interest is less
        $savings = $this->calculator->subtract($oldInterest, $newInterest);

        return round((float)$savings, 2);
    }

    /**
     * Generate new amortization schedule after refinancing
     *
     * @param Loan $loan Loan being refinanced
     * @param array $refinance Refinance proposal
     * @return array New amortization schedule
     */
    public function generateNewSchedule(Loan $loan, array $refinance)
    {
        $principal = $refinance['new_principal'];
        $annualRate = $refinance['new_rate'];
        $months = $refinance['new_term'];
        $payment = $this->calculateNewPayment($loan, $refinance);
        $monthlyRate = $this->calculator->divide($annualRate, 12);

        $balance = $principal;
        $periods = [];

        for ($month = 1; $month <= $months; $month++) {
            $interest = round($balance * $monthlyRate, 2);
            $principal_payment = round($payment - $interest, 2);
            $balance = round($balance - $principal_payment, 2);

            $periods[] = [
                'month' => $month,
                'payment' => $payment,
                'principal' => $principal_payment,
                'interest' => $interest,
                'balance' => max(0, $balance),
            ];

            if ($balance <= 0) {
                break;
            }
        }

        return [
            'periods' => $periods,
            'total_interest' => array_sum(array_column($periods, 'interest')),
            'effective_date' => $refinance['effective_date'],
            'loan_id' => $refinance['loan_id'],
        ];
    }

    /**
     * Approve a refinance proposal
     *
     * @param array $refinance Refinance proposal
     * @param string $approvedBy User ID approving refinance
     * @return array Updated refinance with approval info
     */
    public function approveRefinance(array $refinance, $approvedBy)
    {
        $refinance['status'] = 'APPROVED';
        $refinance['approved_by'] = $approvedBy;
        $refinance['approved_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        return $refinance;
    }

    /**
     * Activate an approved refinance
     *
     * @param array $refinance Approved refinance proposal
     * @return array Refinance with active status
     */
    public function activateRefinance(array $refinance)
    {
        $refinance['status'] = 'ACTIVE';
        $refinance['activated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        return $refinance;
    }

    /**
     * Validate refinance parameters
     *
     * @param Loan $loan Loan being refinanced
     * @param array $params Refinance parameters
     * @return bool True if valid
     */
    public function isValidRefinance(Loan $loan, array $params)
    {
        $newTerm = $params['new_term'] ?? $loan->getMonths();
        $newRate = $params['new_rate'] ?? $loan->getAnnualRate();
        $principalReduction = $params['principal_reduction'] ?? 0;

        // Validate term
        if ($newTerm > self::MAX_TERM_MONTHS) {
            return false;
        }

        // Validate rate
        if ($newRate < self::MIN_RATE || $newRate > self::MAX_RATE) {
            return false;
        }

        // Validate principal reduction
        if ($principalReduction > $loan->getCurrentBalance()) {
            return false;
        }

        return true;
    }

    /**
     * Calculate break-even point for refinancing
     *
     * @param Loan $loan Current loan
     * @param array $refinance Refinance proposal
     * @param float $refinancingCost Cost of refinancing (origination fee, etc)
     * @return float Number of months until refinancing breaks even
     */
    public function calculateBreakEvenMonths(Loan $loan, array $refinance, $refinancingCost = 0)
    {
        $oldRefinance = [
            'new_principal' => $refinance['current_balance'],
            'new_rate' => $refinance['original_rate'],
            'new_term' => $refinance['remaining_months'],
        ];
        $oldPayment = $this->calculateNewPayment($loan, $oldRefinance);
        $newPayment = $this->calculateNewPayment($loan, $refinance);

        // Monthly savings from lower payment
        $monthlySavings = $this->calculator->subtract($oldPayment, $newPayment);

        if ($monthlySavings <= 0) {
            // No break-even if new payment is higher or same
            return 0;
        }

        // Break-even = Total costs / Monthly savings
        $breakEven = $this->calculator->divide($refinancingCost, $monthlySavings);

        return round((float)$breakEven, 1);
    }
}
