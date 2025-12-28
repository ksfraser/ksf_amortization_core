<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Interest Rate Converter - Single Responsibility
 *
 * Converts interest rates between different payment frequencies.
 *
 * ### Formula
 * Converted Rate = Original Rate × (From Periods / To Periods)
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @version   1.0.0
 * @since     2025-12-17
 */
class InterestRateConverter
{
    /**
     * Convert interest rate between frequencies
     *
     * @param float $rate Interest rate
     * @param string $fromFrequency Current frequency
     * @param string $toFrequency Target frequency
     *
     * @return float Converted rate, rounded to 4 decimal places
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function convert(
        float $rate,
        string $fromFrequency,
        string $toFrequency
    ): float
    {
        // Get periods per year for each frequency
        $fromPeriods = PaymentCalculator::getPeriodsPerYear($fromFrequency);
        $toPeriods = PaymentCalculator::getPeriodsPerYear($toFrequency);

        // Simple conversion: rate × (from periods / to periods)
        $convertedRate = $rate * ($fromPeriods / $toPeriods);

        return round($convertedRate, 4);
    }
}
