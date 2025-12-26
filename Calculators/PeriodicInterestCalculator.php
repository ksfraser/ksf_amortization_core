<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Periodic Interest Calculator - Single Responsibility
 *
 * Calculates interest for ONE payment period on a remaining balance.
 *
 * ### Formula
 * Interest = Balance × (Annual Rate / 100) / Periods Per Year
 *
 * ### Example
 * Balance: $100,000
 * Annual Rate: 5%
 * Frequency: Monthly (12 periods per year)
 * Result: 100,000 × 0.05 / 12 = $416.67
 *
 * ### Design
 * - Single Responsibility: Only periodic interest calculation
 * - Pure Function: No state, no side effects
 * - Immutable: No internal state changes
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-17
 */
class PeriodicInterestCalculator
{
    /**
     * Calculate interest for one payment period
     *
     * @param float $balance Remaining loan balance
     * @param float $annualRate Annual interest rate as percentage (e.g., 5.0 for 5%)
     * @param string $frequency Payment frequency ('monthly', 'biweekly', 'weekly', 'daily', etc.)
     *
     * @return float Interest amount for this period, rounded to 2 decimal places
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function calculate(
        float $balance,
        float $annualRate,
        string $frequency
    ): float
    {
        $this->validate($balance, $annualRate, $frequency);

        // Get periods per year for this frequency
        $periodsPerYear = PaymentCalculator::getPeriodsPerYear($frequency);

        // Calculate periodic interest
        // Interest = Balance × (Annual Rate / 100) / Periods Per Year
        $periodicInterest = $balance * ($annualRate / 100) / $periodsPerYear;

        return round($periodicInterest, 2);
    }

    /**
     * Validate all parameters
     *
     * @param float $balance Balance amount
     * @param float $annualRate Annual rate
     * @param string $frequency Frequency name
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function validate(float $balance, float $annualRate, string $frequency): void
    {
        if ($balance < 0) {
            throw new \InvalidArgumentException('Balance cannot be negative, got: ' . $balance);
        }

        if ($annualRate < 0) {
            throw new \InvalidArgumentException('Annual rate cannot be negative, got: ' . $annualRate);
        }

        // Will throw InvalidArgumentException if frequency not recognized
        PaymentCalculator::getPeriodsPerYear($frequency);
    }
}
