<?php

namespace Ksfraser\Amortizations\Strategies;

use Ksfraser\Amortizations\Models\Loan;

/**
 * LoanCalculationStrategy Interface
 *
 * Defines the contract for various loan amortization calculation strategies.
 * Implements Strategy Pattern to allow different calculation algorithms to be
 * selected at runtime.
 *
 * Each strategy calculates:
 * 1. Fixed or variable payment amounts
 * 2. Complete amortization schedules
 * 3. Support for special loan features (balloon, grace periods, etc.)
 *
 * @package Ksfraser\Amortizations\Strategies
 * @since 2.0
 */
interface LoanCalculationStrategy
{
    /**
     * Calculate a single payment amount for the loan.
     *
     * Algorithm varies by strategy:
     * - StandardAmortization: Fixed payment = P * [r(1+r)^n] / [(1+r)^n - 1]
     * - BalloonPayment: Calculated to reach balloon amount in final payment
     * - VariableRate: Adjusts for applicable rate period
     *
     * @param Loan $loan The loan to calculate payment for
     * @param int|null $periodNumber Optional period number for variable rate strategies
     *
     * @return float Payment amount (rounded to 2 decimal places)
     *
     * @throws \InvalidArgumentException If loan data is invalid
     */
    public function calculatePayment(Loan $loan, ?int $periodNumber = null): float;

    /**
     * Generate complete amortization schedule for the loan.
     *
     * Returns array of schedule rows with:
     * - Payment number and date
     * - Principal and interest portions
     * - Balance progression
     * - Any strategy-specific fields (balloon amount, rate period ID, etc.)
     *
     * Algorithm:
     * 1. Calculate payment using calculatePayment()
     * 2. For each period:
     *    - Calculate interest on current balance
     *    - Calculate principal = payment - interest
     *    - Update balance = previous balance - principal
     * 3. Handle final payment adjustment (ensure balance = $0.00 ±$0.02)
     *
     * @param Loan $loan The loan to generate schedule for
     *
     * @return array Array of schedule rows with keys:
     *               [
     *                   'payment_number' => int,
     *                   'payment_date' => string (YYYY-MM-DD),
     *                   'payment_amount' => float,
     *                   'principal' => float,
     *                   'interest' => float,
     *                   'balance' => float,
     *                   'balloon_amount' => float|null (if applicable),
     *                   'rate_period_id' => int|null (if applicable)
     *               ]
     *
     * @throws \InvalidArgumentException If schedule cannot be generated
     */
    public function calculateSchedule(Loan $loan): array;

    /**
     * Determine if this strategy supports a given loan configuration.
     *
     * Used by StrategyFactory to select appropriate strategy.
     *
     * Examples:
     * - StandardAmortization: returns true for any standard loan
     * - BalloonPayment: returns true only if $loan->hasBalloonPayment() === true
     * - VariableRate: returns true only if rate periods exist
     *
     * @param Loan $loan The loan configuration to evaluate
     *
     * @return bool True if this strategy can handle the loan, false otherwise
     */
    public function supports(Loan $loan): bool;
}
