<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Simple Interest Calculator - Single Responsibility
 *
 * Calculates simple interest (no compounding).
 *
 * ### Formula
 * Interest = Principal × (Annual Rate / 100) × Time (in years)
 *
 * ### Example
 * Principal: $100,000
 * Annual Rate: 5%
 * Time: 1 year
 * Result: 100,000 × 0.05 × 1 = $5,000
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @version   1.0.0
 * @since     2025-12-17
 */
class SimpleInterestCalculator
{
    /**
     * Calculate simple interest
     *
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate as percentage
     * @param float $timeInYears Time period in years
     *
     * @return float Simple interest amount, rounded to 2 decimal places
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculate(
        float $principal,
        float $annualRate,
        float $timeInYears
    ): float
    {
        if ($principal < 0) {
            throw new \InvalidArgumentException('Principal cannot be negative');
        }

        if ($annualRate < 0) {
            throw new \InvalidArgumentException('Annual rate cannot be negative');
        }

        if ($timeInYears <= 0) {
            throw new \InvalidArgumentException('Time must be greater than 0');
        }

        // I = P × (R/100) × T
        $interest = $principal * ($annualRate / 100) * $timeInYears;

        return round($interest, 2);
    }
}
