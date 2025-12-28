<?php

namespace Ksfraser\Amortizations\Repositories;

use Ksfraser\Amortizations\Models\RatePeriod;

/**
 * RatePeriodRepository Interface
 *
 * Defines the contract for persisting and retrieving RatePeriod entities.
 * Rate periods define different interest rates that apply during different
 * periods of a variable-rate loan.
 *
 * Relationships:
 * - Many rate periods belong to one loan
 * - Rate periods are ordered by start date
 * - Rate periods can overlap (should not happen, but validate)
 * - Rate periods have optional end dates (null = ongoing)
 *
 * Example data:
 * ```
 * Loan ID 123:
 *   Period 1: 4.5% from 2024-01-01 to 2024-06-30
 *   Period 2: 5.5% from 2024-07-01 to 2024-12-31
 *   Period 3: 6.5% from 2025-01-01 to null (ongoing)
 * ```
 *
 * @package Ksfraser\Amortizations\Repositories
 * @since 2.0
 */
interface RatePeriodRepository
{
    /**
     * Find a rate period by ID.
     *
     * @param int $ratePeriodId The rate period ID
     *
     * @return RatePeriod|null The rate period if found
     *
     * @throws \RuntimeException If database query fails
     */
    public function findById(int $ratePeriodId): ?RatePeriod;

    /**
     * Find all rate periods for a loan.
     *
     * Returns periods ordered by start date (ascending).
     *
     * @param int $loanId The loan ID
     *
     * @return RatePeriod[] Array of rate periods, ordered by start date
     *
     * @throws \RuntimeException If database query fails
     */
    public function findByLoanId(int $loanId): array;

    /**
     * Find active rate period on a specific date.
     *
     * Returns the rate period that applies on this date.
     * Used by VariableRateStrategy to get applicable rate.
     *
     * @param int $loanId The loan ID
     * @param \DateTimeImmutable $date The date to check
     *
     * @return RatePeriod|null The applicable rate period, or null if not found
     *
     * @throws \RuntimeException If database query fails
     */
    public function findActiveOnDate(int $loanId, \DateTimeImmutable $date): ?RatePeriod;

    /**
     * Save a rate period (create new or update existing).
     *
     * If rate period has ID set, perform UPDATE. Otherwise INSERT.
     *
     * Validation:
     * - End date (if present) must be >= start date
     * - Rate must be between 0.0 and 1.0 (0% to 100%)
     * - Start date must be within loan term
     * - No overlapping periods (should validate)
     *
     * @param RatePeriod $period The rate period to save
     *
     * @return int The rate period ID (new or existing)
     *
     * @throws \InvalidArgumentException If period data is invalid
     * @throws \RuntimeException If database operation fails
     */
    public function save(RatePeriod $period): int;

    /**
     * Delete a rate period.
     *
     * Note: Deleting a rate period may require schedule recalculation.
     * Consider this when implementing cascade delete logic.
     *
     * @param int $ratePeriodId The rate period ID to delete
     *
     * @return bool True if deleted, false if not found
     *
     * @throws \RuntimeException If database operation fails
     */
    public function delete(int $ratePeriodId): bool;

    /**
     * Delete all rate periods for a loan.
     *
     * Used when:
     * - Loan is deleted
     * - Converting variable-rate loan to fixed-rate
     *
     * @param int $loanId The loan ID
     *
     * @return int Number of periods deleted
     *
     * @throws \RuntimeException If database operation fails
     */
    public function deleteByLoanId(int $loanId): int;

    /**
     * Get the current applicable rate for a loan.
     *
     * Returns rate on today's date. Used for reporting and analysis.
     *
     * @param int $loanId The loan ID
     *
     * @return float The current applicable rate, or null if no rate found
     *
     * @throws \RuntimeException If database query fails
     */
    public function getCurrentRate(int $loanId): ?float;

    /**
     * Get the next scheduled rate change.
     *
     * Returns the next date when an interest rate change is scheduled.
     * Used for borrower notifications.
     *
     * @param int $loanId The loan ID
     *
     * @return \DateTimeImmutable|null The next rate change date, or null if no changes scheduled
     *
     * @throws \RuntimeException If database query fails
     */
    public function getNextRateChangeDate(int $loanId): ?\DateTimeImmutable;

    /**
     * Check if a loan has variable rates defined.
     *
     * Returns true if loan has one or more rate periods.
     * Used to determine if VariableRateStrategy should be used.
     *
     * @param int $loanId The loan ID
     *
     * @return bool True if loan has variable rates
     *
     * @throws \RuntimeException If database query fails
     */
    public function hasVariableRates(int $loanId): bool;
}
