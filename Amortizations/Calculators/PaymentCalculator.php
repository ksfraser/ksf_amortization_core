<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * PaymentCalculator - Single Responsibility: Calculate Payment Amount
 * 
 * This class has ONE job: calculate the payment amount for a loan using the PMT formula.
 * 
 * ### Formula
 * The PMT formula is:
 * 
 * P = r × PV / (1 - (1 + r)^(-n))
 * 
 * Where:
 * - P = Payment amount
 * - PV = Present value (principal)
 * - r = Interest rate per period
 * - n = Number of periods
 * 
 * ### Example
 * ```php
 * $calculator = new PaymentCalculator();
 * $payment = $calculator->calculate(10000, 5.0, 'monthly', 12);
 * // $payment ≈ 856.07
 * ```
 * 
 * ### Supported Frequencies
 * - 'monthly': 12 periods per year
 * - 'biweekly': 26 periods per year
 * - 'weekly': 52 periods per year
 * - 'daily': 365 periods per year
 * - 'semiannual': 2 periods per year
 * - 'annual': 1 period per year
 * 
 * ### Validation
 * - Principal must be positive
 * - Number of payments must be positive
 * - Frequency must be recognized
 * - Annual rate can be 0 or greater
 * 
 * @author KSF Team
 * @version 1.0.0
 */
class PaymentCalculator
{
    /**
     * Frequency configuration mapping
     * Maps frequency string to periods per year
     */
    private static $frequencyConfig = [
        'monthly' => 12,
        'biweekly' => 26,
        'weekly' => 52,
        'daily' => 365,
        'semiannual' => 2,
        'annual' => 1,
    ];

    /**
     * Calculate the payment amount for a loan
     * 
     * Uses PMT formula to calculate the fixed payment amount.
     * 
     * ### Algorithm
     * 1. Validate all inputs
     * 2. Convert annual rate to periodic rate
     * 3. Apply PMT formula
     * 4. Return rounded payment
     * 
     * @param float $principal The loan principal in dollars
     * @param float $annualRate The annual interest rate as a percentage (e.g., 5.0 for 5%)
     * @param string $frequency Payment frequency (monthly, biweekly, weekly, daily, semiannual, annual)
     * @param int $numberOfPayments Total number of payments
     * 
     * @return float The calculated payment amount
     * 
     * @throws \InvalidArgumentException If any parameter is invalid
     */
    public function calculate($principal, $annualRate, $frequency, $numberOfPayments)
    {
        // Validate inputs
        $this->validateInputs($principal, $annualRate, $frequency, $numberOfPayments);

        // Handle zero interest rate special case
        if ($annualRate == 0) {
            return round($principal / $numberOfPayments, 2);
        }

        // Get periods per year for this frequency
        $periodsPerYear = self::$frequencyConfig[strtolower($frequency)];

        // Convert annual rate to periodic rate
        // Periodic rate = annual rate / 100 / periods per year
        $periodicRate = ($annualRate / 100) / $periodsPerYear;

        // Apply PMT formula
        // P = r × PV / (1 - (1 + r)^(-n))
        $numerator = $periodicRate * $principal;
        $denominator = 1 - pow(1 + $periodicRate, -$numberOfPayments);

        if ($denominator == 0) {
            // Edge case: should not happen with valid inputs
            return round($principal / $numberOfPayments, 2);
        }

        $payment = $numerator / $denominator;

        // Round to 2 decimal places for currency
        return round($payment, 2);
    }

    /**
     * Validate input parameters
     * 
     * @param float $principal The loan principal
     * @param float $annualRate The annual interest rate
     * @param string $frequency Payment frequency
     * @param int $numberOfPayments Number of payments
     * 
     * @throws \InvalidArgumentException If any parameter is invalid
     */
    private function validateInputs($principal, $annualRate, $frequency, $numberOfPayments)
    {
        // Validate principal
        if ($principal <= 0) {
            throw new \InvalidArgumentException('Principal must be greater than 0');
        }

        // Validate number of payments
        if ($numberOfPayments <= 0) {
            throw new \InvalidArgumentException('Number of payments must be greater than 0');
        }

        // Validate frequency
        $frequency = strtolower($frequency);
        if (!isset(self::$frequencyConfig[$frequency])) {
            $supported = implode(', ', array_keys(self::$frequencyConfig));
            throw new \InvalidArgumentException(
                "Unknown frequency: {$frequency}. Supported frequencies: {$supported}"
            );
        }

        // Note: Annual rate can be 0 or positive
        // Negative rates might be allowed in future (deflation scenarios)
    }

    /**
     * Get supported frequencies
     * 
     * @return array List of supported frequency strings
     */
    public static function getSupportedFrequencies()
    {
        return array_keys(self::$frequencyConfig);
    }

    /**
     * Get periods per year for a frequency
     * 
     * @param string $frequency Payment frequency
     * 
     * @return int Number of periods per year
     * 
     * @throws \InvalidArgumentException If frequency not supported
     */
    public static function getPeriodsPerYear($frequency)
    {
        $frequency = strtolower($frequency);
        if (!isset(self::$frequencyConfig[$frequency])) {
            $supported = implode(', ', array_keys(self::$frequencyConfig));
            throw new \InvalidArgumentException(
                "Unknown frequency: {$frequency}. Supported frequencies: {$supported}"
            );
        }
        return self::$frequencyConfig[$frequency];
    }

    /**
     * Get number of days per payment period
     * 
     * Calculates approximate days between payments for a given frequency.
     * Used for date calculations in schedule generation.
     * 
     * @param string $frequency Payment frequency ('monthly', 'biweekly', etc)
     * 
     * @return int Approximate number of days per payment period
     * 
     * @throws \InvalidArgumentException If frequency not supported
     */
    public function getPaymentIntervalDays(string $frequency): int
    {
        $frequency = strtolower(trim($frequency));
        $periodsPerYear = self::getPeriodsPerYear($frequency);

        if ($periodsPerYear == 0) {
            throw new \InvalidArgumentException("Invalid periods per year: $periodsPerYear");
        }

        return (int)round(365 / $periodsPerYear);
    }
}
