<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\LoanEvent;
use DateTimeImmutable;

/**
 * PaymentHistoryTracker - Payment Audit and Reporting Service
 *
 * Maintains a comprehensive audit trail of all payments and loan events,
 * enabling historical analysis and reporting.
 *
 * Features:
 * - Record all payment and event transactions
 * - Query history by date range, event type, or loan
 * - Calculate aggregate statistics (totals, averages, counts)
 * - Track payment status (on-time, late, partial, missed)
 * - Preserve event metadata for forensic analysis
 * - Support for multiple loans with isolated history
 *
 * Architecture:
 * - Uses in-memory storage for test and API scenarios
 * - Can be extended with database backend (Repository pattern)
 * - Immutable event records (audit trail best practice)
 *
 * @author KS Fraser <ksfraser@example.com>
 * @version 1.0.0
 */
class PaymentHistoryTracker
{
    /**
     * In-memory storage for payment events
     * Format: [loan_id => [event1, event2, ...]]
     *
     * @var array<int, array<int, array>>
     */
    private array $history = [];

    /**
     * Record a payment or loan event in the history
     *
     * Events are stored chronologically per loan and include
     * all metadata for audit trail purposes.
     *
     * @param int $loanId The loan identifier
     * @param LoanEvent $event The event to record
     * @return void
     */
    public function recordEvent(int $loanId, LoanEvent $event): void
    {
        if (!isset($this->history[$loanId])) {
            $this->history[$loanId] = [];
        }

        // Parse metadata from event notes if available
        $metadata = [];
        if (!empty($event->notes)) {
            $decoded = json_decode($event->notes, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        // Create event record
        $eventDateStr = $event->event_date instanceof DateTimeImmutable
            ? $event->event_date->format('Y-m-d')
            : date('Y-m-d');

        $record = [
            'loan_id' => $loanId,
            'event_type' => $event->event_type,
            'amount' => $event->amount,
            'event_date' => $event->event_date,
            'event_date_str' => $eventDateStr,
            'status' => $metadata['status'] ?? 'unknown',
            'metadata' => $metadata,
            'recorded_at' => new DateTimeImmutable(),
            'notes' => $event->notes ?? ''
        ];

        $this->history[$loanId][] = $record;
    }

    /**
     * Retrieve all events for a specific loan
     *
     * Returns events in chronological order.
     *
     * @param int $loanId The loan identifier
     * @return array<int, array> Array of event records
     */
    public function getHistory(int $loanId): array
    {
        return $this->history[$loanId] ?? [];
    }

    /**
     * Query history by date range
     *
     * Returns all events that occurred between the specified dates (inclusive).
     *
     * @param int $loanId The loan identifier
     * @param DateTimeImmutable $startDate Start of date range
     * @param DateTimeImmutable $endDate End of date range
     * @return array<int, array> Filtered event records
     */
    public function getHistoryByDateRange(
        int $loanId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        $history = $this->getHistory($loanId);

        $filtered = array_filter($history, function ($record) use ($startDate, $endDate) {
            $eventDate = $record['event_date'];
            if (!$eventDate instanceof DateTimeImmutable) {
                return false;
            }

            return $eventDate >= $startDate && $eventDate <= $endDate;
        });

        return array_values($filtered);
    }

    /**
     * Query history by event type
     *
     * Returns all events of a specific type (regular_payment, extra_payment, skip_payment, etc.).
     *
     * @param int $loanId The loan identifier
     * @param string $eventType The event type to filter by
     * @return array<int, array> Filtered event records
     */
    public function getHistoryByEventType(int $loanId, string $eventType): array
    {
        $history = $this->getHistory($loanId);

        $filtered = array_filter($history, function ($record) use ($eventType) {
            return $record['event_type'] === $eventType;
        });

        return array_values($filtered);
    }

    /**
     * Query history by status
     *
     * Returns all events with a specific status (on_time, late, partial, etc.).
     *
     * @param int $loanId The loan identifier
     * @param string $status The status to filter by
     * @return array<int, array> Filtered event records
     */
    public function getHistoryByStatus(int $loanId, string $status): array
    {
        $history = $this->getHistory($loanId);

        $filtered = array_filter($history, function ($record) use ($status) {
            return $record['status'] === $status;
        });

        return array_values($filtered);
    }

    /**
     * Calculate aggregate statistics for a loan
     *
     * Returns comprehensive statistics including:
     * - total_paid: Sum of all payment amounts
     * - average_payment: Mean payment amount
     * - payment_count: Total number of payments
     * - on_time_count: Payments made on schedule
     * - late_count: Payments made after due date
     * - partial_count: Partial payments
     * - total_interest_paid: Sum of interest portions (if available)
     * - max_payment: Largest single payment
     * - min_payment: Smallest single payment
     *
     * @param int $loanId The loan identifier
     * @return array<string, mixed> Statistics array
     */
    public function getStatistics(int $loanId): array
    {
        $history = $this->getHistory($loanId);

        if (empty($history)) {
            return [
                'total_paid' => 0.00,
                'average_payment' => 0.00,
                'payment_count' => 0,
                'on_time_count' => 0,
                'late_count' => 0,
                'partial_count' => 0,
                'total_interest_paid' => 0.00,
                'max_payment' => 0.00,
                'min_payment' => 0.00
            ];
        }

        // Calculate aggregates
        $amounts = array_map(fn($r) => $r['amount'], $history);
        $totalPaid = array_sum($amounts);
        $paymentCount = count($amounts);
        $averagePayment = $paymentCount > 0 ? $totalPaid / $paymentCount : 0;

        // Count by status
        $onTimeCount = count(array_filter($history, fn($r) => $r['status'] === 'on_time'));
        $lateCount = count(array_filter($history, fn($r) => $r['status'] === 'late'));
        $partialCount = count(array_filter($history, fn($r) => $r['status'] === 'partial'));

        // Calculate min/max
        $maxPayment = !empty($amounts) ? max($amounts) : 0.00;
        $minPayment = !empty($amounts) ? min($amounts) : 0.00;

        // Calculate total interest (if metadata includes interest portion)
        $totalInterest = 0.00;
        foreach ($history as $record) {
            $metadata = $record['metadata'] ?? [];
            if (isset($metadata['interest_paid'])) {
                $totalInterest += (float)$metadata['interest_paid'];
            }
        }

        return [
            'total_paid' => round($totalPaid, 2),
            'average_payment' => round($averagePayment, 2),
            'payment_count' => $paymentCount,
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'partial_count' => $partialCount,
            'total_interest_paid' => round($totalInterest, 2),
            'max_payment' => round($maxPayment, 2),
            'min_payment' => round($minPayment, 2)
        ];
    }

    /**
     * Get statistics for a specific date range
     *
     * Calculates statistics for events within the specified date range only.
     *
     * @param int $loanId The loan identifier
     * @param DateTimeImmutable $startDate Start of date range
     * @param DateTimeImmutable $endDate End of date range
     * @return array<string, mixed> Statistics array
     */
    public function getStatisticsByDateRange(
        int $loanId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        $rangeHistory = $this->getHistoryByDateRange($loanId, $startDate, $endDate);

        if (empty($rangeHistory)) {
            return $this->getEmptyStatistics();
        }

        // Calculate aggregates for range
        $amounts = array_map(fn($r) => $r['amount'], $rangeHistory);
        $totalPaid = array_sum($amounts);
        $paymentCount = count($amounts);
        $averagePayment = $paymentCount > 0 ? $totalPaid / $paymentCount : 0;

        // Count by status
        $onTimeCount = count(array_filter($rangeHistory, fn($r) => $r['status'] === 'on_time'));
        $lateCount = count(array_filter($rangeHistory, fn($r) => $r['status'] === 'late'));
        $partialCount = count(array_filter($rangeHistory, fn($r) => $r['status'] === 'partial'));

        return [
            'total_paid' => round($totalPaid, 2),
            'average_payment' => round($averagePayment, 2),
            'payment_count' => $paymentCount,
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'partial_count' => $partialCount
        ];
    }

    /**
     * Clear all history (useful for testing and resets)
     *
     * @param int|null $loanId If specified, clears only this loan's history
     * @return void
     */
    public function clear(?int $loanId = null): void
    {
        if ($loanId === null) {
            $this->history = [];
        } else {
            unset($this->history[$loanId]);
        }
    }

    /**
     * Get the count of recorded events for a loan
     *
     * @param int $loanId The loan identifier
     * @return int Number of events recorded
     */
    public function getEventCount(int $loanId): int
    {
        return count($this->getHistory($loanId));
    }

    /**
     * Helper to return empty statistics array
     *
     * @return array<string, mixed>
     */
    private function getEmptyStatistics(): array
    {
        return [
            'total_paid' => 0.00,
            'average_payment' => 0.00,
            'payment_count' => 0,
            'on_time_count' => 0,
            'late_count' => 0,
            'partial_count' => 0,
            'total_interest_paid' => 0.00,
            'max_payment' => 0.00,
            'min_payment' => 0.00
        ];
    }
}
