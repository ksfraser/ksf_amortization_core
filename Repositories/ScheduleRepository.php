<?php

namespace Ksfraser\Amortizations\Repositories;

/**
 * ScheduleRepository Interface
 *
 * Defines the contract for persisting and retrieving amortization schedule rows.
 * The schedule represents the payment-by-payment breakdown of a loan.
 *
 * A complete schedule for a 60-month loan contains 60 rows, each with:
 * - Payment number and date
 * - Payment amount (principal + interest)
 * - Principal and interest breakdown
 * - Running balance
 * - Optional: balloon amount, rate period ID, arrears flags
 *
 * Responsibilities:
 * - Store generated schedules efficiently
 * - Retrieve schedules for reporting and analysis
 * - Update schedules when loans are modified (extra payment, rate change)
 * - Archive old schedules when recalculated
 *
 * @package Ksfraser\Amortizations\Repositories
 * @since 2.0
 */
interface ScheduleRepository
{
    /**
     * Save a complete amortization schedule for a loan.
     *
     * Expects array of schedule rows with structure:
     * ```
     * [
     *     [
     *         'payment_number' => int,
     *         'payment_date' => string (YYYY-MM-DD),
     *         'payment_amount' => float,
     *         'principal' => float,
     *         'interest' => float,
     *         'balance' => float,
     *         'balloon_amount' => float|null,
     *         'rate_period_id' => int|null,
     *         'arrears_amount' => float|null,
     *     ],
     *     // ... more rows
     * ]
     * ```
     *
     * Implementation should:
     * 1. Delete old schedule for this loan (if exists)
     * 2. Insert all new rows
     * 3. Validate structure and values
     *
     * @param int $loanId The loan this schedule belongs to
     * @param array $schedule Array of schedule row arrays
     *
     * @return int Number of schedule rows saved
     *
     * @throws \InvalidArgumentException If schedule rows are invalid
     * @throws \RuntimeException If database operation fails
     */
    public function saveSchedule(int $loanId, array $schedule): int;

    /**
     * Get complete schedule for a loan.
     *
     * @param int $loanId The loan ID
     *
     * @return array Array of schedule rows, or empty array if no schedule
     *
     * @throws \RuntimeException If database query fails
     */
    public function getScheduleForLoan(int $loanId): array;

    /**
     * Get a single payment schedule row.
     *
     * @param int $loanId The loan ID
     * @param int $paymentNumber The payment number (1-based index)
     *
     * @return array|null The schedule row if found, null otherwise
     *
     * @throws \RuntimeException If database query fails
     */
    public function getScheduleRow(int $loanId, int $paymentNumber): ?array;

    /**
     * Get schedule rows after a specific payment number.
     *
     * Used for:
     * - Recalculating remaining schedule after extra payment
     * - Viewing remaining payments
     * - Calculating payoff amount
     *
     * @param int $loanId The loan ID
     * @param int $afterPaymentNumber Return rows after this payment number
     *
     * @return array Array of remaining schedule rows
     *
     * @throws \RuntimeException If database query fails
     */
    public function getRemainingSchedule(int $loanId, int $afterPaymentNumber): array;

    /**
     * Get schedule rows for a date range.
     *
     * Useful for:
     * - Payments due this month
     * - Payments due this quarter
     * - Historical payment analysis
     *
     * @param int $loanId The loan ID
     * @param \DateTimeImmutable $startDate Start of date range (inclusive)
     * @param \DateTimeImmutable $endDate End of date range (inclusive)
     *
     * @return array Schedule rows with dates in range
     *
     * @throws \RuntimeException If database query fails
     */
    public function getScheduleByDateRange(
        int $loanId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array;

    /**
     * Delete schedule for a loan.
     *
     * Used when:
     * - Loan is deleted
     * - Schedule is being recalculated
     * - Archiving old schedules
     *
     * @param int $loanId The loan ID
     *
     * @return int Number of rows deleted
     *
     * @throws \RuntimeException If database operation fails
     */
    public function deleteSchedule(int $loanId): int;

    /**
     * Get next payment date for a loan.
     *
     * Returns the earliest payment date >= today.
     *
     * @param int $loanId The loan ID
     *
     * @return \DateTimeImmutable|null The next payment date, or null if schedule not found
     *
     * @throws \RuntimeException If database query fails
     */
    public function getNextPaymentDate(int $loanId): ?\DateTimeImmutable;

    /**
     * Get payoff amount for a loan.
     *
     * Payoff amount = remaining principal + accrued interest to payoff date.
     * This is what borrower must pay to fully satisfy the loan today.
     *
     * @param int $loanId The loan ID
     *
     * @return float The amount needed to pay off loan
     *
     * @throws \RuntimeException If database query fails
     */
    public function getPayoffAmount(int $loanId): float;

    /**
     * Get total interest that will be paid over life of loan.
     *
     * Sum of all interest amounts in schedule.
     *
     * @param int $loanId The loan ID
     *
     * @return float Total interest to be paid
     *
     * @throws \RuntimeException If database query fails
     */
    public function getTotalInterest(int $loanId): float;
}
