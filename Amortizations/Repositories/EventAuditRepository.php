<?php

namespace Ksfraser\Amortizations\Repositories;

/**
 * EventAuditRepository Interface
 *
 * Defines contract for persisting and retrieving loan event audit trails.
 * Implements Repository Pattern to abstract data access layer from business logic.
 *
 * Responsibilities:
 * - Record loan events with full metadata
 * - Query events by loan, date range, or type
 * - Generate statistics from event history
 * - Support audit trail compliance
 *
 * @author KS Fraser <ksfraser@example.com>
 * @version 1.0.0
 */
interface EventAuditRepository
{
    /**
     * Record a loan event in the audit trail
     *
     * @param int $loanId The loan identifier
     * @param array $eventData Event details (type, date, amount, status, metadata, etc.)
     * @return int The ID of the recorded event
     * @throws \RuntimeException If database operation fails
     */
    public function recordEvent(int $loanId, array $eventData): int;

    /**
     * Retrieve events for a specific loan
     *
     * @param int $loanId The loan identifier
     * @param int|null $limit Maximum number of events to return
     * @param int|null $offset Number of events to skip
     * @return array<int, array> Array of event records
     * @throws \RuntimeException If database query fails
     */
    public function getEventsByLoan(int $loanId, ?int $limit = null, ?int $offset = null): array;

    /**
     * Retrieve events by type for a specific loan
     *
     * @param int $loanId The loan identifier
     * @param string $eventType The event type to filter by
     * @return array<int, array> Array of matching event records
     * @throws \RuntimeException If database query fails
     */
    public function getEventsByType(int $loanId, string $eventType): array;

    /**
     * Retrieve events by status for a specific loan
     *
     * @param int $loanId The loan identifier
     * @param string $status The status to filter by (on_time, late, partial, etc.)
     * @return array<int, array> Array of matching event records
     * @throws \RuntimeException If database query fails
     */
    public function getEventsByStatus(int $loanId, string $status): array;

    /**
     * Retrieve events within a date range
     *
     * @param int $loanId The loan identifier
     * @param string $startDate Start date in YYYY-MM-DD format
     * @param string $endDate End date in YYYY-MM-DD format
     * @return array<int, array> Array of events in the date range
     * @throws \RuntimeException If database query fails
     */
    public function getEventsByDateRange(int $loanId, string $startDate, string $endDate): array;

    /**
     * Get event count for a loan
     *
     * @param int $loanId The loan identifier
     * @return int Number of events recorded
     * @throws \RuntimeException If database query fails
     */
    public function getEventCount(int $loanId): int;

    /**
     * Update an event record
     *
     * @param int $eventId The event identifier
     * @param array $updateData Fields to update
     * @return bool True if successful
     * @throws \RuntimeException If database operation fails
     */
    public function updateEvent(int $eventId, array $updateData): bool;

    /**
     * Delete an event record (audit log trail)
     *
     * WARNING: Deleting audit records compromises audit trail integrity.
     * Consider soft-delete or retention policies instead.
     *
     * @param int $eventId The event identifier
     * @return bool True if successful
     * @throws \RuntimeException If database operation fails
     */
    public function deleteEvent(int $eventId): bool;

    /**
     * Clear all events for a loan (typically for testing or data cleanup)
     *
     * @param int $loanId The loan identifier
     * @return int Number of records deleted
     * @throws \RuntimeException If database operation fails
     */
    public function clearEventsByLoan(int $loanId): int;

    /**
     * Get the most recent event for a loan
     *
     * @param int $loanId The loan identifier
     * @return array|null The most recent event or null if none found
     * @throws \RuntimeException If database query fails
     */
    public function getMostRecentEvent(int $loanId): ?array;

    /**
     * Get statistics for events within a date range
     *
     * Returns aggregated data suitable for reporting:
     * - total_events
     * - total_amount_paid
     * - on_time_count
     * - late_count
     * - missed_count
     * - average_payment
     *
     * @param int $loanId The loan identifier
     * @param string $startDate Start date in YYYY-MM-DD format
     * @param string $endDate End date in YYYY-MM-DD format
     * @return array<string, mixed> Statistics array
     * @throws \RuntimeException If database query fails
     */
    public function getStatisticsByDateRange(int $loanId, string $startDate, string $endDate): array;
}
