<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTimeImmutable;

/**
 * FeeAmortizationService - Loan Fee and Charge Management Service
 *
 * Manages all types of loan fees and charges including:
 * - One-time fees: origination, closing costs, documentation
 * - Recurring fees: monthly servicing, insurance, miscellaneous
 * - Supports fee amortization over loan term
 * - GL posting integration for accounting
 * - Effective rate calculation with fees
 *
 * Features:
 * - Add/remove fees dynamically
 * - Calculate total borrowing cost
 * - Amortize fees across schedule
 * - Generate GL posting entries
 * - Calculate effective interest rate
 * - Schedule generation with fee components
 *
 * @package Ksfraser\Amortizations\Services
 */
class FeeAmortizationService
{
    /**
     * Fees by loan ID
     * @var array
     */
    private $fees = [];

    /**
     * Fee counter for unique IDs
     * @var int
     */
    private $feeIdCounter = 1000;

    /**
     * Decimal calculator for precision
     * @var DecimalCalculator
     */
    private $calculator;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Add a fee to a loan
     *
     * @param int $loanId Loan identifier
     * @param string $type Fee type (origination, closing, servicing, insurance, misc)
     * @param float $amount Fee amount
     * @param string $description Fee description
     * @param string $frequency Fee frequency (one_time or monthly)
     * @return array Fee record
     */
    public function addFee($loanId, $type, $amount, $description, $frequency = 'one_time')
    {
        if (!isset($this->fees[$loanId])) {
            $this->fees[$loanId] = [];
        }

        $feeId = ++$this->feeIdCounter;

        $fee = [
            'fee_id' => $feeId,
            'loan_id' => $loanId,
            'type' => $type,
            'amount' => round($amount, 2),
            'description' => $description,
            'frequency' => $frequency,
            'added_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'status' => 'ACTIVE',
        ];

        $this->fees[$loanId][] = $fee;

        return $fee;
    }

    /**
     * Remove a fee from a loan
     *
     * @param int $loanId Loan identifier
     * @param int $feeId Fee identifier to remove
     * @return bool Success
     */
    public function removeFee($loanId, $feeId)
    {
        if (!isset($this->fees[$loanId])) {
            return false;
        }

        $this->fees[$loanId] = array_values(
            array_filter(
                $this->fees[$loanId],
                fn($f) => $f['fee_id'] !== $feeId
            )
        );

        return true;
    }

    /**
     * Get all fees for a loan
     *
     * @param int $loanId Loan identifier
     * @return array Array of fees
     */
    public function getAllFees($loanId)
    {
        return $this->fees[$loanId] ?? [];
    }

    /**
     * Calculate total one-time fees
     *
     * @param array $fees Array of fees
     * @return float Total one-time fees
     */
    private function calculateOneTimeFees(array $fees)
    {
        $total = 0;
        foreach ($fees as $fee) {
            if ($fee['frequency'] === 'one_time') {
                $total = $this->calculator->add($total, $fee['amount']);
            }
        }
        return (float)$total;
    }

    /**
     * Calculate total monthly fees
     *
     * @param array $fees Array of fees
     * @return float Total monthly fees
     */
    private function calculateMonthlyFees(array $fees)
    {
        $total = 0;
        foreach ($fees as $fee) {
            if ($fee['frequency'] === 'monthly') {
                $total = $this->calculator->add($total, $fee['amount']);
            }
        }
        return (float)$total;
    }

    /**
     * Calculate total borrowing cost (principal + all fees)
     *
     * @param Loan $loan Loan object
     * @param array $fees Array of fees
     * @return float Total borrowing cost
     */
    public function calculateTotalBorrowingCost(Loan $loan, array $fees)
    {
        $principal = $loan->getPrincipal();
        $oneTimeFees = $this->calculateOneTimeFees($fees);
        $monthlyFees = $this->calculateMonthlyFees($fees);
        $months = $loan->getMonths();

        $totalMonthlyFees = $this->calculator->multiply($monthlyFees, $months);
        $total = $this->calculator->add($principal, $oneTimeFees);
        $total = $this->calculator->add($total, $totalMonthlyFees);

        return round((float)$total, 2);
    }

    /**
     * Calculate total interest including impact of fees
     *
     * @param Loan $loan Loan object
     * @param array $fees Array of fees
     * @return float Total interest paid
     */
    public function calculateTotalInterestWithFees(Loan $loan, array $fees)
    {
        $principal = $loan->getPrincipal();
        $annualRate = $loan->getAnnualRate();
        $months = $loan->getMonths();
        $monthlyRate = $annualRate / 12;

        // Calculate interest on original principal
        $balance = $principal;
        $totalInterest = 0;

        for ($month = 0; $month < $months; $month++) {
            $interest = round($balance * $monthlyRate, 2);
            $totalInterest = $this->calculator->add($totalInterest, $interest);
        }

        // Add monthly fees (they accrue interest implicitly)
        $monthlyFees = $this->calculateMonthlyFees($fees);
        $totalMonthlyFees = $this->calculator->multiply($monthlyFees, $months);

        return round((float)$this->calculator->add($totalInterest, $totalMonthlyFees), 2);
    }

    /**
     * Amortize a one-time fee over loan term
     *
     * @param array $fee Fee to amortize
     * @param int $months Loan term in months
     * @return array Amortization schedule
     */
    public function amortizeFee(array $fee, $months)
    {
        $monthlyAmount = round($fee['amount'] / $months, 2);

        $schedule = [];
        for ($month = 1; $month <= $months; $month++) {
            $schedule[] = $monthlyAmount;
        }

        return [
            'fee_id' => $fee['fee_id'],
            'type' => $fee['type'],
            'total_amount' => $fee['amount'],
            'monthly_amount' => $monthlyAmount,
            'months' => $months,
            'schedule' => $schedule,
        ];
    }

    /**
     * Generate amortization schedule including fees
     *
     * @param Loan $loan Loan object
     * @param array $fees Array of fees
     * @return array Schedule with fee components
     */
    public function generateScheduleWithFees(Loan $loan, array $fees)
    {
        $principal = $loan->getPrincipal();
        $annualRate = $loan->getAnnualRate();
        $months = $loan->getMonths();
        $monthlyRate = $annualRate / 12;

        // Calculate one-time fees (amortized)
        $oneTimeFees = $this->calculateOneTimeFees($fees);
        $monthlyOneTimeFee = round($oneTimeFees / $months, 2);

        $monthlyRecurringFees = $this->calculateMonthlyFees($fees);

        // Calculate base payment
        $rPlus1 = 1 + $monthlyRate;
        $rPlus1Pow = bcpow($rPlus1, $months, 10);
        $numerator = $principal * $monthlyRate * $rPlus1Pow;
        $denominator = $rPlus1Pow - 1;
        $basePayment = round($numerator / $denominator, 2);

        $balance = $principal;
        $periods = [];

        for ($month = 1; $month <= $months; $month++) {
            $interest = round($balance * $monthlyRate, 2);
            $principalPayment = round($basePayment - $interest, 2);
            $balance = round($balance - $principalPayment, 2);

            $totalFees = round($monthlyOneTimeFee + $monthlyRecurringFees, 2);

            $periods[] = [
                'month' => $month,
                'principal' => $principalPayment,
                'interest' => $interest,
                'base_payment' => $basePayment,
                'fees_charged' => $totalFees,
                'fee_breakdown' => [
                    'amortized_one_time' => $monthlyOneTimeFee,
                    'recurring' => $monthlyRecurringFees,
                ],
                'total_payment' => round($basePayment + $totalFees, 2),
                'balance' => max(0, $balance),
            ];

            if ($balance <= 0) {
                break;
            }
        }

        return [
            'periods' => $periods,
            'loan_id' => $loan->getId(),
        ];
    }

    /**
     * Generate GL posting entries for fees
     *
     * @param Loan $loan Loan object
     * @param array $fees Array of fees
     * @return array GL posting entries
     */
    public function generateGLPostings(Loan $loan, array $fees)
    {
        $postings = [];

        foreach ($fees as $fee) {
            if ($fee['status'] !== 'ACTIVE') {
                continue;
            }

            // One-time fees
            if ($fee['frequency'] === 'one_time') {
                $postings[] = [
                    'loan_id' => $loan->getId(),
                    'fee_type' => $fee['type'],
                    'type' => $fee['type'],
                    'frequency' => 'one_time',
                    'amount' => $fee['amount'],
                    'account_type' => 'debit',
                    'account' => $this->getAssetAccount($fee['type']),
                    'description' => $fee['description'],
                    'posting_date' => (new DateTimeImmutable())->format('Y-m-d'),
                ];

                $postings[] = [
                    'loan_id' => $loan->getId(),
                    'fee_type' => $fee['type'],
                    'type' => $fee['type'],
                    'frequency' => 'one_time',
                    'amount' => $fee['amount'],
                    'account_type' => 'credit',
                    'account' => $this->getIncomeAccount($fee['type']),
                    'description' => 'Fee income: ' . $fee['description'],
                    'posting_date' => (new DateTimeImmutable())->format('Y-m-d'),
                ];
            } else {
                // Monthly fees
                $postings[] = [
                    'loan_id' => $loan->getId(),
                    'fee_type' => $fee['type'],
                    'type' => $fee['type'],
                    'frequency' => 'monthly',
                    'amount' => $fee['amount'],
                    'account_type' => 'debit',
                    'account' => $this->getAssetAccount($fee['type']),
                    'description' => $fee['description'],
                ];

                $postings[] = [
                    'loan_id' => $loan->getId(),
                    'fee_type' => $fee['type'],
                    'type' => $fee['type'],
                    'frequency' => 'monthly',
                    'amount' => $fee['amount'],
                    'account_type' => 'credit',
                    'account' => $this->getIncomeAccount($fee['type']),
                    'description' => 'Fee income: ' . $fee['description'],
                ];
            }
        }

        return $postings;
    }

    /**
     * Get asset account code for fee type
     *
     * @param string $feeType Fee type
     * @return string Account code
     */
    private function getAssetAccount($feeType)
    {
        return match ($feeType) {
            'origination' => '1105',  // Origination fees receivable
            'closing' => '1106',      // Closing fees receivable
            'servicing' => '1107',    // Servicing fees receivable
            'insurance' => '1108',    // Insurance fees receivable
            default => '1100',        // General fees receivable
        };
    }

    /**
     * Get income account code for fee type
     *
     * @param string $feeType Fee type
     * @return string Account code
     */
    private function getIncomeAccount($feeType)
    {
        return match ($feeType) {
            'origination' => '4105',  // Origination fee income
            'closing' => '4106',      // Closing fee income
            'servicing' => '4107',    // Servicing fee income
            'insurance' => '4108',    // Insurance fee income
            default => '4100',        // General fee income
        };
    }

    /**
     * Calculate effective interest rate including fees
     *
     * @param Loan $loan Loan object
     * @param array $fees Array of fees
     * @return float Effective annual rate (as decimal)
     */
    public function calculateEffectiveRate(Loan $loan, array $fees)
    {
        $principal = $loan->getPrincipal();
        $nominaRate = $loan->getAnnualRate();
        $months = $loan->getMonths();

        $oneTimeFees = $this->calculateOneTimeFees($fees);

        // Net proceeds after fees
        $netProceeds = round($principal - $oneTimeFees, 2);

        // Effective monthly rate calculation (simplified)
        // Effective rate ≈ nominal rate + (annual fees / principal)
        $annualFeeImpact = $this->calculator->divide($oneTimeFees, $principal);
        $effectiveRate = $nominaRate + $annualFeeImpact;

        return round((float)$effectiveRate, 4);
    }
}
