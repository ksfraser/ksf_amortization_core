<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Interest Calculator - Facade/Legacy Interface
 *
 * **DEPRECATED:** Use specific calculator classes instead.
 * This class now acts as a facade that delegates to the 6 SRP calculator classes.
 *
 * ### Backwards Compatibility
 * Maintains the original interface for existing code while delegating to:
 * - PeriodicInterestCalculator - Periodic interest calculations
 * - SimpleInterestCalculator - Simple interest (I = P×R×T)
 * - CompoundInterestCalculator - Compound interest with frequencies
 * - DailyInterestCalculator - Daily interest & accrual
 * - EffectiveRateCalculator - APY/APR conversions
 * - InterestRateConverter - Rate frequency conversions
 *
 * ### Interest Types Supported
 * - **Periodic**: Interest for one payment period (monthly, weekly, etc.)
 * - **Simple**: I = P × R × T (no compounding)
 * - **Compound**: A = P(1 + r/n)^(nt) (with compounding)
 * - **Daily**: For per diem interest calculations
 * - **Accrual**: Interest from date A to date B
 *
 * ### Usage Example (Legacy)
 * ```php
 * $interestCalc = new InterestCalculator();
 * $interest = $interestCalc->calculatePeriodicInterest(100000, 5.0, 'monthly');
 * // Result: 416.67
 * ```
 *
 * ### Migration Path
 * Instead of using this class directly, use:
 * ```php
 * $periodicCalc = new PeriodicInterestCalculator();
 * $interest = $periodicCalc->calculate(100000, 5.0, 'monthly');
 * ```
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @author    KSF Development Team
 * @version   2.0.0 (Refactored to delegate to SRP classes)
 * @since     2025-12-17
 * @deprecated Use specific calculator classes: PeriodicInterestCalculator, SimpleInterestCalculator, etc.
 */
class InterestCalculator
{
    /**
     * @var PeriodicInterestCalculator Delegates periodic interest calculations
     */
    private $periodicCalculator;

    /**
     * @var SimpleInterestCalculator Delegates simple interest calculations
     */
    private $simpleCalculator;

    /**
     * @var CompoundInterestCalculator Delegates compound interest calculations
     */
    private $compoundCalculator;

    /**
     * @var DailyInterestCalculator Delegates daily interest calculations
     */
    private $dailyCalculator;

    /**
     * @var EffectiveRateCalculator Delegates APY/effective rate calculations
     */
    private $effectiveRateCalculator;

    /**
     * @var InterestRateConverter Delegates rate conversions
     */
    private $rateConverter;

    /**
     * Constructor - Initialize delegated calculators
     *
     * All dependencies are created internally for backwards compatibility.
     * For dependency injection, use individual calculator classes directly.
     */
    public function __construct()
    {
        $this->periodicCalculator = new PeriodicInterestCalculator();
        $this->simpleCalculator = new SimpleInterestCalculator();
        $this->compoundCalculator = new CompoundInterestCalculator();
        $this->dailyCalculator = new DailyInterestCalculator();
        $this->effectiveRateCalculator = new EffectiveRateCalculator();
        $this->rateConverter = new InterestRateConverter();
    }

    /**
     * Calculate periodic interest on remaining balance
     *
     * Delegates to PeriodicInterestCalculator.
     *
     * @param float $balance Remaining balance
     * @param float $annualRate Annual interest rate as percentage
     * @param string $frequency Payment frequency ('monthly', 'biweekly', etc.)
     *
     * @return float Interest amount for one period
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculatePeriodicInterest(
        float $balance,
        float $annualRate,
        string $frequency
    ): float
    {
        return $this->periodicCalculator->calculate($balance, $annualRate, $frequency);
    }

    /**
     * Calculate simple interest
     *
     * Delegates to SimpleInterestCalculator.
     *
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate as percentage
     * @param float $timeInYears Time period in years
     *
     * @return float Simple interest amount
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateSimpleInterest(
        float $principal,
        float $annualRate,
        float $timeInYears
    ): float
    {
        return $this->simpleCalculator->calculate($principal, $annualRate, $timeInYears);
    }

    /**
     * Calculate compound interest
     *
     * Delegates to CompoundInterestCalculator.
     *
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate as percentage
     * @param int $periods Number of compounding periods
     * @param string $frequency Compounding frequency
     *
     * @return float Compound interest earned
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateCompoundInterest(
        float $principal,
        float $annualRate,
        int $periods,
        string $frequency
    ): float
    {
        return $this->compoundCalculator->calculate($principal, $annualRate, $periods, $frequency);
    }

    /**
     * Calculate daily interest (for per diem calculations)
     *
     * Delegates to DailyInterestCalculator.
     *
     * @param float $balance Account balance
     * @param float $annualRate Annual interest rate as percentage
     *
     * @return float Daily interest amount
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateDailyInterest(
        float $balance,
        float $annualRate
    ): float
    {
        return $this->dailyCalculator->calculateDaily($balance, $annualRate);
    }

    /**
     * Calculate total interest in schedule
     *
     * Sums interest_amount field from all schedule rows.
     *
     * @param array $schedule Array of schedule rows
     *
     * @return float Total interest paid
     */
    public function calculateTotalInterest(array $schedule): float
    {
        $total = 0;

        foreach ($schedule as $row) {
            if (isset($row['interest_amount']) && is_numeric($row['interest_amount'])) {
                $total += $row['interest_amount'];
            }
        }

        return round($total, 2);
    }

    /**
     * Calculate interest accrual between two dates
     *
     * Delegates to DailyInterestCalculator.
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
    public function calculateInterestAccrual(
        float $balance,
        float $annualRate,
        string $startDate,
        string $endDate
    ): float
    {
        return $this->dailyCalculator->calculateAccrual($balance, $annualRate, $startDate, $endDate);
    }

    /**
     * Calculate APY (Annual Percentage Yield) from APR
     *
     * Delegates to EffectiveRateCalculator.
     *
     * @param float $apr Annual Percentage Rate as percentage
     * @param string $frequency Compounding frequency
     *
     * @return float APY as percentage
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateAPYFromAPR(
        float $apr,
        string $frequency
    ): float
    {
        return $this->effectiveRateCalculator->calculateAPY($apr, $frequency);
    }

    /**
     * Calculate effective interest rate for a frequency
     *
     * Delegates to EffectiveRateCalculator.
     *
     * @param float $nominalRate Nominal rate as percentage
     * @param string $frequency Compounding frequency
     *
     * @return float Effective rate as percentage
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function calculateEffectiveRate(
        float $nominalRate,
        string $frequency
    ): float
    {
        return $this->effectiveRateCalculator->calculateAPY($nominalRate, $frequency);
    }

    /**
     * Convert interest rate between frequencies
     *
     * Delegates to InterestRateConverter.
     *
     * @param float $rate Interest rate (as percentage or decimal)
     * @param string $fromFrequency Current frequency
     * @param string $toFrequency Target frequency
     *
     * @return float Converted rate
     *
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function convertRate(
        float $rate,
        string $fromFrequency,
        string $toFrequency
    ): float
    {
        return $this->rateConverter->convert($rate, $fromFrequency, $toFrequency);
    }
}
