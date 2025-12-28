<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Daily Interest Calculator - Single Responsibility
 *
 * Calculates daily interest and accrual between dates.
 *
 * ### Formulas
 * Daily Interest: D = Balance × (Annual Rate / 100) / 365
 * Accrual: A = Daily Interest × Number of Days
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @version   1.0.0
 * @since     2025-12-17
 */
class DailyInterestCalculator
{
    /**
     * Calculate daily interest
     *
     * @param float $balance Account balance
     * @param float $annualRate Annual interest rate as percentage
     *
     * @return float Daily interest amount, rounded to 2 decimal places
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateDaily(float $balance, float $annualRate): float
    {
        if ($balance < 0) {
            throw new \InvalidArgumentException('Balance cannot be negative');
        }

        if ($annualRate < 0) {
            throw new \InvalidArgumentException('Annual rate cannot be negative');
        }

        // Daily rate = Annual rate / 365 days
        $dailyInterest = $balance * ($annualRate / 100) / 365;

        return round($dailyInterest, 2);
    }

    /**
     * Calculate interest accrual between two dates
     *
     * @param float $balance Account balance
     * @param float $annualRate Annual interest rate as percentage
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     *
     * @return float Interest accrued between dates
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateAccrual(
        float $balance,
        float $annualRate,
        string $startDate,
        string $endDate
    ): float
    {
        // Calculate days between dates
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        // Check if end is before start
        if ($end < $start) {
            throw new \InvalidArgumentException('End date must be after or equal to start date');
        }
        
        $interval = $start->diff($end);
        $days = $interval->days;

        // Daily interest
        $dailyInterest = $this->calculateDaily($balance, $annualRate);

        // Accrual = daily interest × number of days
        $accrual = $dailyInterest * $days;

        return round($accrual, 2);
    }
}
