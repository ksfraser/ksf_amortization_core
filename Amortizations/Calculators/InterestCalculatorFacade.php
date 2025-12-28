<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Interest Calculator Facade - Backwards Compatibility Layer
 *
 * This facade provides the original `InterestCalculator` interface
 * by delegating to the 6 new Single Responsibility calculator classes.
 *
 * ### Purpose
 * Enables gradual migration from monolithic `InterestCalculator` to
 * specialized SRP calculator classes without breaking existing code.
 *
 * ### Old Interface (InterestCalculator)
 * ```php
 * $calc = new InterestCalculator();
 * $interest = $calc->calculatePeriodicInterest($balance, $rate, $frequency);
 * $interest = $calc->calculateSimpleInterest($principal, $rate, $years);
 * $interest = $calc->calculateCompoundInterest($principal, $rate, $periods, $freq);
 * $daily = $calc->calculateDailyInterest($balance, $rate);
 * $accrual = $calc->calculateInterestAccrual($balance, $rate, $start, $end);
 * $apy = $calc->calculateAPYFromAPR($apr, $frequency);
 * $rate = $calc->convertRate($rate, $from, $to);
 * ```
 *
 * ### New Interface (Individual Calculators)
 * ```php
 * $periodic = new PeriodicInterestCalculator();
 * $periodic->calculate($balance, $rate, $frequency);
 *
 * $simple = new SimpleInterestCalculator();
 * $simple->calculate($principal, $rate, $years);
 *
 * // ... other calculators
 * ```
 *
 * ### Migration Path
 * 1. Replace `new InterestCalculator()` with `new InterestCalculatorFacade()`
 * 2. Facade delegates to new SRP classes
 * 3. All tests pass without code changes
 * 4. Gradually migrate code to use specific calculators
 * 5. Remove facade and InterestCalculator when migration complete
 *
 * ### Design
 * - **Type:** Facade Pattern
 * - **Responsibility:** Delegate to SRP calculators
 * - **Testing:** Can be fully tested through delegation
 * - **Deprecation:** InterestCalculator marked @deprecated in favor of specific calculators
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-17
 * @deprecated Use specific calculator classes instead: PeriodicInterestCalculator,
 *             SimpleInterestCalculator, CompoundInterestCalculator, etc.
 */
class InterestCalculatorFacade
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
     * @var InterestRateConverter Delegates rate conversions
     */
    private $rateConverter;

    /**
     * @var EffectiveRateCalculator Delegates APY/effective rate calculations
     */
    private $effectiveRateCalculator;

    /**
     * @var int Decimal precision for calculations
     */
    private $precision = 4;

    /**
     * Constructor - Initialize all delegated calculators
     */
    public function __construct()
    {
        $this->periodicCalculator = new PeriodicInterestCalculator();
        $this->simpleCalculator = new SimpleInterestCalculator();
        $this->compoundCalculator = new CompoundInterestCalculator();
        $this->dailyCalculator = new DailyInterestCalculator();
        $this->rateConverter = new InterestRateConverter();
        $this->effectiveRateCalculator = new EffectiveRateCalculator();
    }

    /**
     * Set calculation precision
     *
     * @param int $precision Decimal places
     *
     * @return void
     */
    public function setPrecision(int $precision): void
    {
        $this->precision = max(2, $precision);
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
     * Simple Interest: I = P × R × T
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
     * Compound Interest: A = P(1 + r/n)^(nt), Interest = A - P
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
     * Daily Interest: D = Balance × (Annual Rate / 100) / 365
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
     * Calculates interest accrued from startDate to endDate
     * using daily interest calculation.
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
     * APY = (1 + APR/n)^n - 1, where n = compounding periods per year
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
     * Same as APY - converts nominal to effective rate.
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
     * Converts a rate from one frequency to another,
     * accounting for compounding differences.
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
