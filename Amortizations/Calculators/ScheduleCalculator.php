<?php
namespace Ksfraser\Amortizations\Calculators;

/**
 * Schedule Calculator - Single Responsibility: Calculate Amortization Schedules
 *
 * Pure calculation class for generating amortization schedules.
 * NO database access, NO side effects.
 *
 * ### Responsibility
 * - Generate payment schedules with flexible payment frequencies
 * - Support different interest calculation frequencies
 * - Calculate interest, principal, and remaining balance for each payment
 * - Pure functions: no persistence, no external state changes
 * - Delegates periodic interest calculation to PeriodicInterestCalculator (SRP)
 *
 * ### Dependencies
 * - PaymentCalculator: Calculate fixed payment amounts
 * - PeriodicInterestCalculator: Calculate periodic interest on balance
 *
 * ### Usage Example
 * ```php
 * $paymentCalc = new PaymentCalculator();
 * $interestCalc = new PeriodicInterestCalculator();
 * $scheduleCalc = new ScheduleCalculator($paymentCalc, $interestCalc);
 *
 * $schedule = $scheduleCalc->generateSchedule(
 *     principal: 100000,
 *     annualRate: 5.0,
 *     paymentFrequency: 'monthly',
 *     numberOfPayments: 360,
 *     startDate: '2025-01-01'
 * );
 *
 * foreach ($schedule as $row) {
 *     echo $row['payment_number'] . ': ' . $row['payment_date'] . ' -> ' . $row['payment_amount'];
 * }
 * ```
 *
 * ### Schedule Row Structure
 * ```php
 * [
 *     'payment_number'     => 1,
 *     'payment_date'       => '2025-02-01',
 *     'payment_amount'     => 536.82,
 *     'interest_amount'    => 416.67,
 *     'principal_amount'   => 120.15,
 *     'remaining_balance'  => 99879.85,
 * ]
 * ```
 *
 * ### Design Principles
 * - Single Responsibility: Only schedule calculation, no persistence
 * - Dependency Injection: All dependencies injected
 * - Immutability: No internal state changes
 * - Pure Functions: Same input always = same output
 * - SRP Delegation: Uses specialized calculators for specific tasks
 *
 * @package   Ksfraser\Amortizations\Calculators
 * @author    KSF Development Team
 * @version   1.1.0 (Refactored to delegate interest to PeriodicInterestCalculator)
 * @since     2025-12-17
 */
class ScheduleCalculator
{
    /**
     * @var PaymentCalculator Payment calculator dependency
     */
    private $paymentCalculator;

    /**
     * @var PeriodicInterestCalculator Interest calculator for periodic interest calculations
     */
    private $periodicInterestCalculator;

    /**
     * @var int Decimal precision for calculations
     */
    private $precision = 4;

    /**
     * Constructor with dependency injection
     *
     * @param PaymentCalculator $paymentCalculator Calculator for payment amounts
     * @param PeriodicInterestCalculator $periodicInterestCalculator Calculator for periodic interest (optional)
     *
     * @throws InvalidArgumentException If paymentCalculator is null
     */
    public function __construct(
        PaymentCalculator $paymentCalculator,
        PeriodicInterestCalculator $periodicInterestCalculator = null
    )
    {
        if (!$paymentCalculator) {
            throw new InvalidArgumentException('PaymentCalculator required');
        }
        $this->paymentCalculator = $paymentCalculator;
        $this->periodicInterestCalculator = $periodicInterestCalculator ?? new PeriodicInterestCalculator();
    }

    /**
     * Set calculation precision (decimal places)
     *
     * @param int $precision Number of decimal places
     *
     * @return void
     */
    public function setPrecision(int $precision): void
    {
        $this->precision = max(2, $precision);
    }

    /**
     * Generate complete amortization schedule
     *
     * ### Algorithm
     * 1. Validate all inputs
     * 2. Calculate fixed payment amount
     * 3. For each payment period:
     *    a. Calculate interest on remaining balance
     *    b. Calculate principal portion
     *    c. Update balance
     *    d. Add row to schedule
     * 4. Adjust final payment to ensure balance = $0
     *
     * ### Parameters
     * - `$principal`: Loan amount (must be > 0)
     * - `$annualRate`: Annual interest rate as percentage (e.g., 5.0 for 5%)
     * - `$paymentFrequency`: Payment frequency ('monthly', 'biweekly', etc.)
     * - `$numberOfPayments`: Total payments to generate
     * - `$startDate`: First payment date (default: today)
     * - `$interestCalcFrequency`: How often interest compounds (default: same as payments)
     *
     * @param float $principal Loan principal amount
     * @param float $annualRate Annual interest rate as percentage
     * @param string $paymentFrequency Payment frequency name
     * @param int $numberOfPayments Total number of payments
     * @param string $startDate First payment date (YYYY-MM-DD)
     * @param string $interestCalcFrequency Interest calculation frequency
     *
     * @return array Array of schedule rows with payment details
     *
     * @throws InvalidArgumentException If parameters invalid
     */
    public function generateSchedule(
        float $principal,
        float $annualRate,
        string $paymentFrequency,
        int $numberOfPayments,
        string $startDate = null,
        string $interestCalcFrequency = null
    ): array
    {
        // Validate inputs
        $this->validateInputs($principal, $annualRate, $paymentFrequency, $numberOfPayments);

        // Default start date to today
        if (!$startDate) {
            $startDate = date('Y-m-d');
        }

        // Default interest frequency to payment frequency
        if (!$interestCalcFrequency) {
            $interestCalcFrequency = $paymentFrequency;
        }

        // Calculate fixed payment amount
        $paymentAmount = $this->paymentCalculator->calculate(
            $principal,
            $annualRate,
            $paymentFrequency,
            $numberOfPayments
        );

        // Initialize schedule generation
        $schedule = [];
        $balance = $principal;
        $currentDate = new \DateTime($startDate);
        $daysPerPaymentPeriod = $this->paymentCalculator->getPaymentIntervalDays($paymentFrequency);
        $periodsPerYear = $this->paymentCalculator->getPeriodsPerYear($interestCalcFrequency);

        // Generate each payment row
        for ($paymentNum = 1; $paymentNum <= $numberOfPayments; $paymentNum++) {
            // Calculate interest for this period using PeriodicInterestCalculator
            // Interest = Balance * (annual rate / 100) / (periods per year)
            $interestAmount = $this->periodicInterestCalculator->calculate(
                $balance,
                $annualRate,
                $interestCalcFrequency
            );

            // Calculate principal portion
            $principalAmount = $paymentAmount - $interestAmount;

            // Adjust final payment to ensure balance reaches exactly zero
            if ($paymentNum == $numberOfPayments) {
                $principalAmount = $balance;
                $paymentAmount = $principalAmount + $interestAmount;
            }

            // Update remaining balance
            $newBalance = $balance - $principalAmount;
            // Avoid negative balance due to rounding
            if ($newBalance < 0) {
                $newBalance = 0;
            }

            // Add row to schedule
            $schedule[] = [
                'payment_number'    => $paymentNum,
                'payment_date'      => $currentDate->format('Y-m-d'),
                'payment_amount'    => round($paymentAmount, 2),
                'interest_amount'   => round($interestAmount, 2),
                'principal_amount'  => round($principalAmount, 2),
                'remaining_balance' => round($newBalance, 2),
            ];

            // Move to next payment date
            $balance = $newBalance;
            $currentDate->add(new \DateInterval('P' . $daysPerPaymentPeriod . 'D'));
        }

        return $schedule;
    }

    /**
     * Validate input parameters
     *
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate
     * @param string $paymentFrequency Payment frequency
     * @param int $numberOfPayments Number of payments
     *
     * @return void
     *
     * @throws InvalidArgumentException If validation fails
     */
    private function validateInputs(
        float $principal,
        float $annualRate,
        string $paymentFrequency,
        int $numberOfPayments
    ): void
    {
        if ($principal <= 0) {
            throw new \InvalidArgumentException('Principal must be greater than 0, got: ' . $principal);
        }

        if ($numberOfPayments <= 0) {
            throw new \InvalidArgumentException('Number of payments must be greater than 0, got: ' . $numberOfPayments);
        }

        if ($annualRate < 0) {
            throw new \InvalidArgumentException('Annual rate cannot be negative, got: ' . $annualRate);
        }

        // Validate frequency is supported (will throw if invalid)
        try {
            $this->paymentCalculator->getPeriodsPerYear($paymentFrequency);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Invalid payment frequency: ' . $paymentFrequency);
        }
    }
}
