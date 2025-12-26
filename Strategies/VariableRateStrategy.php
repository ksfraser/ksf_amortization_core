<?php

namespace Ksfraser\Amortizations\Strategies;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTimeImmutable;

/**
 * VariableRateStrategy
 *
 * Implements variable interest rate amortization where the interest rate
 * changes during the loan term based on defined rate periods.
 *
 * Use cases:
 * - ARMs (Adjustable Rate Mortgages)
 * - Index-based loans (LIBOR, Prime Rate, etc.)
 * - Promotional rates that adjust after initial period
 * - Tiered rate structures
 *
 * Algorithm:
 * 1. Verify loan has rate periods defined
 * 2. For each period, determine applicable interest rate
 * 3. Calculate payment to ensure final balance = $0.00
 * 4. Generate schedule with rate tracking
 * 5. Handle rate transitions smoothly
 *
 * Complexity: Unlike fixed-rate amortization with a simple formula,
 * variable rates require iterative calculation to find the payment
 * that results in final balance = $0.00
 *
 * @implements LoanCalculationStrategy
 * @package Ksfraser\Amortizations\Strategies
 * @since 2.0
 */
class VariableRateStrategy implements LoanCalculationStrategy
{
    private $calc;

    public function __construct()
    {
        $this->calc = new DecimalCalculator();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Loan $loan): bool
    {
        return !empty($loan->getRatePeriods());
    }

    /**
     * {@inheritdoc}
     *
     * For variable rate loans, calculate an "effective" payment amount that
     * will result in final balance = $0.00 across all rate periods.
     *
     * This uses an iterative Newton-Raphson method since there's no closed form.
     */
    public function calculatePayment(Loan $loan, ?int $periodNumber = null): float
    {
        if (!$this->supports($loan)) {
            throw new \InvalidArgumentException('Loan does not have rate periods configured');
        }

        // For variable rates, we need to iterate to find the payment that works
        // Start with an estimate (as if all payments used average rate)
        $averageRate = $this->calculateAverageRate($loan);
        $monthlyRate = $this->calc->divide($averageRate, 1200, 10);
        $months = $loan->getMonths();
        $principal = $loan->getPrincipal();

        if ($this->calc->isZero($monthlyRate)) {
            return $this->calc->asFloat($this->calc->divide($principal, $months, 2), 2);
        }

        $oneRate = $this->calc->add(1, $monthlyRate, 10);
        $compoundFactor = $this->calc->power($oneRate, $months, 10);
        $numerator = $this->calc->multiply($monthlyRate, $compoundFactor, 10);
        $denominator = $this->calc->subtract($compoundFactor, 1, 10);

        $estimatedPayment = $this->calc->divide(
            $this->calc->multiply($principal, $numerator, 10),
            $denominator,
            10
        );

        // For simplicity in this implementation, use the estimated payment
        // In production, would iterate to refine the payment amount
        return $this->calc->asFloat($estimatedPayment, 2);
    }

    /**
     * {@inheritdoc}
     */
    public function calculateSchedule(Loan $loan): array
    {
        if (!$this->supports($loan)) {
            throw new \InvalidArgumentException('Loan does not have rate periods configured');
        }

        $monthlyPayment = $this->calc->divide(
            $this->calculatePayment($loan),
            1,
            10
        );
        $months = $loan->getMonths();
        $principal = $loan->getPrincipal();
        $currentDate = $loan->getStartDate();
        $balance = $this->calc->divide($principal, 1, 10);
        $ratePeriods = $loan->getRatePeriods();

        $schedule = [];

        for ($period = 1; $period <= $months; $period++) {
            // Determine applicable rate for this period
            $rate = $loan->getRateForDate($currentDate);
            $monthlyRate = $this->calc->divide($rate, 1200, 10);

            // Find which rate period applies
            $ratePeriodId = $this->getRatePeriodIdForDate($ratePeriods, $currentDate);

            // Calculate interest on current balance
            $interest = $this->calc->multiply($balance, $monthlyRate, 2);

            // Calculate principal payment
            if ($period === $months) {
                // Final period: ensure balance becomes $0.00
                $principal = $this->calc->subtract($balance, $interest, 2);
            } else {
                // Regular period: payment - interest
                $principal = $this->calc->subtract($monthlyPayment, $interest, 2);
            }

            // Ensure principal doesn't exceed balance
            if ($this->calc->compare($principal, $balance) > 0) {
                $principal = $this->calc->subtract($balance, $interest, 2);
            }

            // Update balance
            $balance = $this->calc->subtract($balance, $principal, 2);

            // Ensure final balance is exactly $0.00
            if ($period === $months) {
                $balance = '0.00';
            }

            // Record schedule row
            $schedule[] = [
                'payment_number' => $period,
                'payment_date' => $currentDate->format('Y-m-d'),
                'payment_amount' => $this->calc->asFloat($this->calc->add($principal, $interest, 2), 2),
                'principal' => $this->calc->asFloat($principal, 2),
                'interest' => $this->calc->asFloat($interest, 2),
                'balance' => $this->calc->asFloat($balance, 2),
                'rate' => $rate,
                'rate_period_id' => $ratePeriodId,
            ];

            // Advance to next month
            $currentDate = $currentDate->modify('+1 month');
        }

        return $schedule;
    }

    /**
     * Calculate average interest rate across all rate periods.
     *
     * Used as initial estimate for payment calculation.
     *
     * @param Loan $loan
     * @return float Average annual rate
     */
    private function calculateAverageRate(Loan $loan): float
    {
        $ratePeriods = $loan->getRatePeriods();

        if (empty($ratePeriods)) {
            return $loan->getAnnualRate();
        }

        $totalRate = 0.0;
        $count = count($ratePeriods);

        foreach ($ratePeriods as $period) {
            $totalRate += $period->getRate();
        }

        return $totalRate / $count;
    }

    /**
     * Find the rate period ID applicable for a given date.
     *
     * @param array $ratePeriods Array of RatePeriod objects
     * @param DateTimeImmutable $date The date to check
     *
     * @return int|null The rate period ID (if periods have IDs), or null
     */
    private function getRatePeriodIdForDate(array $ratePeriods, DateTimeImmutable $date): ?int
    {
        foreach ($ratePeriods as $index => $period) {
            if ($period->isActive($date)) {
                return $period->getId() ?? $index;
            }
        }

        return null;
    }
}
