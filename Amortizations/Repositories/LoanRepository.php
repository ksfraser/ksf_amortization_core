<?php

namespace Ksfraser\Amortizations\Repositories;

use Ksfraser\Amortizations\Models\Loan;

/**
 * LoanRepository Interface
 *
 * Defines the contract for persisting and retrieving Loan aggregate roots.
 * Implements Repository Pattern to abstract data access layer from business logic.
 *
 * Responsibilities:
 * - Retrieve loans by ID
 * - Save (create/update) loans
 * - Delete loans
 * - Query loans by various criteria (borrower ID, status, etc.)
 *
 * Platform implementations:
 * - FADataProvider (FrontAccounting)
 * - WordPressDataProvider (WordPress)
 * - SuiteCRMDataProvider (SuiteCRM)
 *
 * @package Ksfraser\Amortizations\Repositories
 * @since 2.0
 */
interface LoanRepository
{
    /**
     * Find a loan by its ID.
     *
     * @param int $loanId The loan ID to retrieve
     *
     * @return Loan|null The loan if found, null otherwise
     *
     * @throws \RuntimeException If database query fails
     */
    public function findById(int $loanId): ?Loan;

    /**
     * Find all loans for a borrower.
     *
     * @param int $borrowerId The borrower ID
     *
     * @return Loan[] Array of loans belonging to borrower
     *
     * @throws \RuntimeException If database query fails
     */
    public function findByBorrowerId(int $borrowerId): array;

    /**
     * Find loans by status.
     *
     * Common statuses:
     * - 'active' - Loan is current and payments are being made
     * - 'completed' - Final payment made, balance = $0.00
     * - 'delinquent' - Payments are overdue
     * - 'defaulted' - Borrower has defaulted
     * - 'paid_off' - Loan fully satisfied
     *
     * @param string $status The loan status to filter by
     *
     * @return Loan[] Array of loans with given status
     *
     * @throws \RuntimeException If database query fails
     */
    public function findByStatus(string $status): array;

    /**
     * Save a loan (create new or update existing).
     *
     * If loan has ID set, perform UPDATE. Otherwise INSERT.
     *
     * Responsibilities:
     * 1. Validate loan data
     * 2. Calculate any derived fields (balance, payments_remaining)
     * 3. Insert/update in database
     * 4. Set ID if new insert
     * 5. Persist rate periods and arrears relationships
     *
     * @param Loan $loan The loan to save
     *
     * @return int The loan ID (new or existing)
     *
     * @throws \InvalidArgumentException If loan data is invalid
     * @throws \RuntimeException If database operation fails
     */
    public function save(Loan $loan): int;

    /**
     * Delete a loan and all related data.
     *
     * Cascade delete:
     * - Schedule entries
     * - Rate periods
     * - Arrears records
     * - Events
     *
     * @param int $loanId The loan ID to delete
     *
     * @return bool True if deleted, false if not found
     *
     * @throws \RuntimeException If database operation fails
     */
    public function delete(int $loanId): bool;

    /**
     * Get count of active loans.
     *
     * @return int Number of active loans
     *
     * @throws \RuntimeException If database query fails
     */
    public function countActive(): int;

    /**
     * Get total value of all active loans.
     *
     * @return float Sum of principal amounts for active loans
     *
     * @throws \RuntimeException If database query fails
     */
    public function getTotalActiveBalance(): float;

    /**
     * Find loans due for payment on a specific date.
     *
     * @param \DateTimeImmutable $date The payment date to check
     *
     * @return Loan[] Loans with payments due on this date
     *
     * @throws \RuntimeException If database query fails
     */
    public function findDueOnDate(\DateTimeImmutable $date): array;
}
