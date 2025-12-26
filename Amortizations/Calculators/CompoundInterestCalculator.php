<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Compound Interest Calculator - Single Responsibility
 *
 * Calculates compound interest with periodic compounding.
 *
 * ### Formula
 * A = P(1 + r/n)^(nt)
 * Interest = A - P
 *
 * Where:
 * - A = Final amount
 * - P = Principal
 * - r = Annual rate (as decimal)
 * - n = Compounding periods per year
 * - t = Time in years
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @version   1.0.0
 * @since     2025-12-17
 */
class CompoundInterestCalculator
{
    /**
     * Calculate compound interest
     *
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate as percentage
     * @param int $periods Total number of compounding periods
     * @param string $frequency Compounding frequency
     *
     * @return float Compound interest earned, rounded to 2 decimal places
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculate(
        float $principal,
        float $annualRate,
        int $periods,
        string $frequency
    ): float
    {
        if ($principal < 0) {
            throw new \InvalidArgumentException('Principal cannot be negative');
        }

        if ($annualRate < 0) {
            throw new \InvalidArgumentException('Annual rate cannot be negative');
        }

        if ($periods <= 0) {
            throw new \InvalidArgumentException('Periods must be greater than 0');
        }

        // Get periods per year for this frequency
        $periodsPerYear = PaymentCalculator::getPeriodsPerYear($frequency);

        // Periodic rate
        $periodicRate = ($annualRate / 100) / $periodsPerYear;

        // Final amount: A = P(1 + r)^n
        $finalAmount = $principal * pow(1 + $periodicRate, $periods);

        // Interest = A - P
        $interest = $finalAmount - $principal;

        return round($interest, 2);
    }
}
