<?php

namespace Ksfraser\Amortizations\Repositories;

use Ksfraser\Amortizations\Models\Arrears;

/**
 * ArrearsRepository Interface
 *
 * Defines the contract for persisting and retrieving Arrears records.
 * Arrears track overdue payments, including principal, interest, and penalties.
 *
 * Arrears are created when:
 * - Borrower makes a partial payment (shortfall becomes arrears)
 * - Payment is skipped
 * - Late fees or penalties are assessed
 *
 * Arrears are cleared when:
 * - Subsequent payment covers the arrears amount
 * - Loan is paid off
 *
 * Relationships:
 * - Many arrears records can belong to one loan
 * - Arrears are ordered by creation date
 * - Active arrears have amount > 0
 * - Cleared arrears have amount = 0 (but kept for history)
 *
 * @package Ksfraser\Amortizations\Repositories
 * @since 2.0
 */
interface ArrearsRepository
{
    /**
     * Find an arrears record by ID.
     *
     * @param int $arrearsId The arrears record ID
     *
     * @return Arrears|null The arrears record if found
     *
     * @throws \RuntimeException If database query fails
     */
    public function findById(int $arrearsId): ?Arrears;

    /**
     * Find all arrears records for a loan.
     *
     * Returns both active and cleared arrears, ordered by creation date (newest first).
     *
     * @param int $loanId The loan ID
     *
     * @return Arrears[] Array of all arrears records
     *
     * @throws \RuntimeException If database query fails
     */
    public function findByLoanId(int $loanId): array;

    /**
     * Find active (non-zero) arrears for a loan.
     *
     * Returns only arrears with amount > 0.00.
     * Used to determine if payments must cover arrears before principal.
     *
     * @param int $loanId The loan ID
     *
     * @return Arrears[] Array of active arrears records
     *
     * @throws \RuntimeException If database query fails
     */
    public function findActiveByLoanId(int $loanId): array;

    /**
     * Get total active arrears amount for a loan.
     *
     * Sum of all active arrears (amount > 0.00).
     * Used for reporting and payoff calculations.
     *
     * @param int $loanId The loan ID
     *
     * @return float Total arrears amount
     *
     * @throws \RuntimeException If database query fails
     */
    public function getTotalArrearsForLoan(int $loanId): float;

    /**
     * Save an arrears record (create new or update existing).
     *
     * If arrears has ID set, perform UPDATE. Otherwise INSERT.
     *
     * When creating:
     * - Set creation timestamp
     * - Initialize update timestamp as null
     *
     * When updating:
     * - Update the "updated_at" timestamp
     * - Preserve creation timestamp
     *
     * @param Arrears $arrears The arrears record to save
     *
     * @return int The arrears ID (new or existing)
     *
     * @throws \InvalidArgumentException If arrears data is invalid
     * @throws \RuntimeException If database operation fails
     */
    public function save(Arrears $arrears): int;

    /**
     * Delete an arrears record.
     *
     * Note: Usually you'd update arrears to 0.00 amount rather than delete,
     * to preserve history. Delete should only be used for cleanup.
     *
     * @param int $arrearsId The arrears record ID to delete
     *
     * @return bool True if deleted, false if not found
     *
     * @throws \RuntimeException If database operation fails
     */
    public function delete(int $arrearsId): bool;

    /**
     * Delete all arrears records for a loan.
     *
     * Used when:
     * - Loan is deleted
     * - Arrears records need to be cleared (rare)
     *
     * @param int $loanId The loan ID
     *
     * @return int Number of arrears deleted
     *
     * @throws \RuntimeException If database operation fails
     */
    public function deleteByLoanId(int $loanId): int;

    /**
     * Get all loans with active arrears.
     *
     * Used for:
     * - Delinquency reports
     * - Collection lists
     * - Risk analysis
     *
     * @return array Array of loan IDs with active arrears
     *
     * @throws \RuntimeException If database query fails
     */
    public function getLoansWithActiveArrears(): array;

    /**
     * Get arrears overdue for a specified number of days.
     *
     * Returns arrears where days_overdue >= specified days.
     * Used for escalation (30-day notice, 60-day notice, etc.)
     *
     * @param int $daysOverdue Minimum days overdue
     *
     * @return Arrears[] Array of arrears meeting criteria
     *
     * @throws \RuntimeException If database query fails
     */
    public function findByDaysOverdue(int $daysOverdue): array;

    /**
     * Get total penalties assessed across all arrears for a loan.
     *
     * Sum of penalty_amount for all arrears records.
     *
     * @param int $loanId The loan ID
     *
     * @return float Total penalties
     *
     * @throws \RuntimeException If database query fails
     */
    public function getTotalPenaltiesForLoan(int $loanId): float;

    /**
     * Check if a loan has any active arrears.
     *
     * @param int $loanId The loan ID
     *
     * @return bool True if loan has active arrears
     *
     * @throws \RuntimeException If database query fails
     */
    public function hasActiveArrears(int $loanId): bool;
}
