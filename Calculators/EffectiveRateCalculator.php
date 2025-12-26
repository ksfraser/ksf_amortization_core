<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Effective Rate Calculator - Single Responsibility
 *
 * Converts nominal (APR) rates to effective (APY) rates
 * accounting for compounding frequency.
 *
 * ### Formula
 * APY = (1 + APR/n)^n - 1
 * Where n = compounding periods per year
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @version   1.0.0
 * @since     2025-12-17
 */
class EffectiveRateCalculator
{
    /**
     * Calculate APY (Annual Percentage Yield) from APR
     *
     * @param float $apr Annual Percentage Rate as percentage
     * @param string $frequency Compounding frequency
     *
     * @return float APY as percentage, rounded to 4 decimal places
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateAPY(float $apr, string $frequency): float
    {
        if ($apr < 0) {
            throw new \InvalidArgumentException('APR cannot be negative');
        }

        $periodsPerYear = PaymentCalculator::getPeriodsPerYear($frequency);
        $periodicRate = ($apr / 100) / $periodsPerYear;

        // APY = (1 + r)^n - 1
        $apy = pow(1 + $periodicRate, $periodsPerYear) - 1;

        // Return as percentage
        return round($apy * 100, 4);
    }

    /**
     * Alias for calculateAPY (commonly used name)
     *
     * @param float $apr Annual Percentage Rate as percentage
     * @param string $frequency Compounding frequency
     *
     * @return float Effective rate as percentage
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateEffectiveRate(float $apr, string $frequency): float
    {
        return $this->calculateAPY($apr, $frequency);
    }
}
