<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Api\ApiResponse;
use Ksfraser\Amortizations\Repositories\EventRepositoryInterface;
use Ksfraser\Amortizations\Repositories\LoanRepositoryInterface;

/**
 * EventRecordingService: Orchestrate event recording with full workflow
 * 
 * Complete event recording process:
 * [1] Validate event data
 * [2] Create event record
 * [3] Update loan status
 * [4] Trigger recalculation (if needed)
 * [5] Propagate changes
 */
class EventRecordingService
{
    /**
     * @var EventRepositoryInterface
     */
    private $eventRepository;
    /**
     * @var LoanRepositoryInterface
     */
    private $loanRepository;
    /**
     * @var EventValidator
     */
    private $eventValidator;
    /**
     * @var ScheduleRecalculationService
     */
    private $recalculationService;

    public function __construct(
        EventRepositoryInterface $eventRepository,
        LoanRepositoryInterface $loanRepository,
        EventValidator $eventValidator,
        ScheduleRecalculationService $recalculationService
    ) {
        $this->eventRepository = $eventRepository;
        $this->loanRepository = $loanRepository;
        $this->eventValidator = $eventValidator;
        $this->recalculationService = $recalculationService;
    }

    /**
     * Record event and handle all related updates
     *
     * @param int $loanId
     * @param array $eventData
     * @return array Event data or error response
     */
    public function recordEvent(int $loanId, array $eventData): array
    {
        // [1] Validate Event
        $loan = $this->loanRepository->get($loanId);
        if (!$loan) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'Loan not found',
                'errors' => ['loan_id' => 'Loan does not exist']
            ];
        }

        $validationErrors = $this->eventValidator->validate($eventData, $loan);
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Event validation failed',
                'errors' => $validationErrors
            ];
        }

        // [2] Create Event Record
        $event = $this->eventRepository->record([
            'loan_id' => $loanId,
            'event_type' => $eventData['event_type'],
            'event_date' => $eventData['event_date'],
            'amount' => $eventData['amount'] ?? null,
            'months_to_skip' => $eventData['months_to_skip'] ?? null,
            'new_rate' => $eventData['new_rate'] ?? null,
            'adjustment_type' => $eventData['adjustment_type'] ?? null,
            'value' => $eventData['value'] ?? null,
            'applied_to' => $eventData['applied_to'] ?? null,
            'notes' => $eventData['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // [3] Update Loan Status
        $loan->last_event_date = $event->event_date;
        $loan->event_count = ($loan->event_count ?? 0) + 1;
        $loan->last_modified = date('Y-m-d H:i:s');

        // [4] Trigger Recalculation (if needed)
        if ($this->recalculationService->shouldRecalculate($eventData['event_type'])) {
            $loan = $this->recalculationService->recalculate(
                $loan,
                $event,
                $eventData
            );
        }

        // Update loan with all changes
        $this->loanRepository->update($loanId, (array)$loan);

        // [5] Propagate Changes
        $this->propagateChanges($loanId, $event, $eventData);

        return [
            'success' => true,
            'status_code' => 201,
            'message' => 'Event recorded successfully',
            'data' => [
                'event' => $this->eventToArray($event),
                'loan' => $this->loanToArray($loan)
            ]
        ];
    }

    /**
     * Propagate changes to related records
     *
     * @param int $loanId
     * @param object $event
     * @param array $eventData
     * @return void
     */
    private function propagateChanges(int $loanId, object $event, array $eventData): void
    {
        // Log the event occurrence
        $this->logEventOccurrence($loanId, $event, $eventData);
        
        // Update event statistics
        $this->updateEventStatistics($loanId, $event);
        
        // Trigger any webhooks/notifications (if configured)
        $this->triggerNotifications($loanId, $event);
    }

    /**
     * Log event occurrence for audit trail
     *
     * @param int $loanId
     * @param object $event
     * @param array $eventData
     * @return void
     */
    private function logEventOccurrence(int $loanId, object $event, array $eventData): void
    {
        // This would normally write to an audit log table
        // For now, we're tracking it in the event record itself
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'loan_id' => $loanId,
            'event_id' => $event->id ?? 0,
            'event_type' => $event->event_type,
            'event_date' => $event->event_date,
            'action' => 'recorded'
        ];
        
        // In production, this would be written to a log table
        error_log(json_encode($logEntry));
    }

    /**
     * Update event statistics on loan
     *
     * @param int $loanId
     * @param object $event
     * @return void
     */
    private function updateEventStatistics(int $loanId, object $event): void
    {
        // Track event statistics for analytics
        // Count by type, track trends, etc.
        // In production, this would update analytics tables
    }

    /**
     * Trigger notifications/webhooks
     *
     * @param int $loanId
     * @param object $event
     * @return void
     */
    private function triggerNotifications(int $loanId, object $event): void
    {
        // Trigger webhooks for external systems
        // Send notifications to interested parties
        // Update dashboards/reports
        // In production, this would call webhook handlers
    }

    /**
     * Get event count for loan
     *
     * @param int $loanId
     * @return int
     */
    public function getEventCount(int $loanId): int
    {
        $events = $this->eventRepository->listByLoan($loanId, 1, 1);
        return $events['total'] ?? 0;
    }

    /**
     * Get events by type for loan
     *
     * @param int $loanId
     * @param string $eventType
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getEventsByType(
        int $loanId,
        string $eventType,
        int $page = 1,
        int $perPage = 20
    ): array {
        $allEvents = $this->eventRepository->listByLoan($loanId, $page, $perPage);
        
        $filtered = array_filter(
            $allEvents['events'] ?? [],
            fn($event) => $event->event_type === $eventType
        );
        
        return [
            'events' => array_values($filtered),
            'total' => count($filtered),
            'event_type' => $eventType
        ];
    }

    /**
     * Get events in date range
     *
     * @param int $loanId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getEventsByDateRange(
        int $loanId,
        string $startDate,
        string $endDate
    ): array {
        // Fetch all events for loan
        $allEvents = $this->eventRepository->listByLoan($loanId, 1, 1000);
        
        // Filter by date range
        $filtered = array_filter(
            $allEvents['events'] ?? [],
            fn($event) => $event->event_date >= $startDate && $event->event_date <= $endDate
        );
        
        return [
            'events' => array_values($filtered),
            'total' => count($filtered),
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    /**
     * Calculate impact of event
     *
     * @param int $loanId
     * @param string $eventType
     * @param array $eventData
     * @return array Impact metrics
     */
    public function calculateEventImpact(
        int $loanId,
        string $eventType,
        array $eventData
    ): array {
        $loan = $this->loanRepository->get($loanId);
        if (!$loan) {
            return ['error' => 'Loan not found'];
        }

        $impact = [
            'event_type' => $eventType,
            'original_balance' => $loan->current_balance ?? $loan->principal,
            'original_term' => $loan->term_months ?? 0,
            'original_rate' => $loan->interest_rate
        ];

        switch ($eventType) {
            case 'extra_payment':
                $amount = $eventData['amount'] ?? 0;
                $impact['payment_amount'] = $amount;
                $impact['new_balance'] = max(0, ($loan->current_balance ?? $loan->principal) - $amount);
                $impact['balance_reduction'] = $amount;
                $impact['interest_savings'] = $this->recalculationService->calculateInterestSavings(
                    $loan,
                    $amount
                );
                break;

            case 'skip_payment':
                $months = $eventData['months_to_skip'] ?? 1;
                $impact['months_skipped'] = $months;
                $impact['new_term'] = ($loan->term_months ?? 0) + $months;
                $impact['interest_accrual'] = $this->calculateAccruedInterest(
                    $loan,
                    $months
                );
                break;

            case 'rate_change':
                $newRate = $eventData['new_rate'] ?? $loan->interest_rate;
                $impact['old_rate'] = $loan->interest_rate;
                $impact['new_rate'] = $newRate;
                $impact['rate_change_basis_points'] = ($newRate - $loan->interest_rate) * 10000;
                break;
        }

        return $impact;
    }

    /**
     * Calculate accrued interest for period
     *
     * @param object $loan
     * @param int $months
     * @return float
     */
    private function calculateAccruedInterest(object $loan, int $months): float
    {
        $monthlyRate = $loan->interest_rate / 12;
        $balance = $loan->current_balance ?? $loan->principal;
        $totalInterest = 0;

        for ($i = 0; $i < $months; $i++) {
            $interest = $balance * $monthlyRate;
            $totalInterest += $interest;
            $balance += $interest;
        }

        return round($totalInterest, 2);
    }

    /**
     * Convert event object to array
     *
     * @param object $event
     * @return array
     */
    private function eventToArray(object $event): array
    {
        return [
            'id' => $event->id ?? null,
            'loan_id' => $event->loan_id ?? null,
            'event_type' => $event->event_type ?? null,
            'event_date' => $event->event_date ?? null,
            'amount' => $event->amount ?? null,
            'notes' => $event->notes ?? null,
            'created_at' => $event->created_at ?? null
        ];
    }

    /**
     * Convert loan object to array
     *
     * @param object $loan
     * @return array
     */
    private function loanToArray(object $loan): array
    {
        return [
            'id' => $loan->id ?? null,
            'principal' => $loan->principal ?? null,
            'current_balance' => $loan->current_balance ?? null,
            'interest_rate' => $loan->interest_rate ?? null,
            'term_months' => $loan->term_months ?? null,
            'start_date' => $loan->start_date ?? null,
            'last_event_date' => $loan->last_event_date ?? null,
            'event_count' => $loan->event_count ?? 0,
            'needs_recalculation' => $loan->needs_recalculation ?? false
        ];
    }
}
