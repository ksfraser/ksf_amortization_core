<?php

namespace Ksfraser\Amortizations\Services;

use DateTimeImmutable;

class EventNotificationService
{
    /**
     * @var array
     */
    private $subscribers = [];
    /**
     * @var array
     */
    private $events = [];
    /**
     * @var array
     */
    private $scheduledEvents = [];

    /**
     * Register an event subscriber
     */
    public function registerSubscriber(array $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    /**
     * Unregister an event subscriber
     */
    public function unregisterSubscriber(string $name): void
    {
        $this->subscribers = array_filter(
            $this->subscribers,
            fn($s) => $s['name'] !== $name
        );
    }

    /**
     * Get all subscribers
     */
    public function getSubscribers(): array
    {
        return $this->subscribers;
    }

    /**
     * Trigger a payment due event
     */
    public function triggerPaymentDueEvent(
        int $loanId,
        int $month,
        float $amount,
        string $dueDate
    ): array {
        $event = [
            'type' => 'payment_due',
            'loan_id' => $loanId,
            'month' => $month,
            'amount' => $amount,
            'due_date' => $dueDate,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->events[] = $event;
        $this->notifySubscribers($event);

        return $event;
    }

    /**
     * Trigger a payoff milestone event
     */
    public function triggerPayoffMilestoneEvent(
        int $loanId,
        float $payoffPercentage,
        string $date
    ): array {
        $event = [
            'type' => 'payoff_milestone',
            'loan_id' => $loanId,
            'payoff_percentage' => $payoffPercentage,
            'date' => $date,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->events[] = $event;
        $this->notifySubscribers($event);

        return $event;
    }

    /**
     * Trigger a rate change event
     */
    public function triggerRateChangeEvent(
        int $loanId,
        float $oldRate,
        float $newRate,
        string $effectiveDate
    ): array {
        $event = [
            'type' => 'rate_change',
            'loan_id' => $loanId,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'effective_date' => $effectiveDate,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->events[] = $event;
        $this->notifySubscribers($event);

        return $event;
    }

    /**
     * Get all events for a specific loan
     */
    public function getEventsForLoan(int $loanId): array
    {
        return array_filter(
            $this->events,
            fn($e) => $e['loan_id'] === $loanId
        );
    }

    /**
     * Filter events by type
     */
    public function filterEventsByType(string $type): array
    {
        return array_filter(
            $this->events,
            fn($e) => $e['type'] === $type
        );
    }

    /**
     * Generate a notification from an event
     */
    public function generateNotification(array $event): array
    {
        $subject = $this->generateNotificationSubject($event);
        $message = $this->generateNotificationMessage($event);
        $channels = $this->determineNotificationChannels($event);

        return [
            'subject' => $subject,
            'message' => $message,
            'channels' => $channels,
            'event_type' => $event['type'],
            'loan_id' => $event['loan_id'],
        ];
    }

    /**
     * Generate notification subject
     */
    private function generateNotificationSubject(array $event): string
    {
        $type = $event['type'];
        if ($type === 'payment_due') {
            return "Payment Due - Loan #{$event['loan_id']} - Month {$event['month']}";
        } elseif ($type === 'payoff_milestone') {
            $percent = round($event['payoff_percentage'] * 100);
            return "Milestone Reached - {$percent}% Paid";
        } elseif ($type === 'rate_change') {
            return "Rate Change Notice";
        }
        return 'Loan Event Notification';
    }

    /**
     * Generate notification message
     */
    private function generateNotificationMessage(array $event): string
    {
        $type = $event['type'];
        if ($type === 'payment_due') {
            return "Your payment of \${$event['amount']} is due on {$event['due_date']}";
        } elseif ($type === 'payoff_milestone') {
            $percent = round($event['payoff_percentage'] * 100);
            return "Congratulations! You have paid off {$percent}% of your loan.";
        } elseif ($type === 'rate_change') {
            $oldRate = round($event['old_rate'] * 100, 2);
            $newRate = round($event['new_rate'] * 100, 2);
            return "Your interest rate has changed from {$oldRate}% to {$newRate}% effective {$event['effective_date']}";
        }
        return 'Your loan account requires attention';
    }

    /**
     * Determine notification channels
     */
    private function determineNotificationChannels(array $event): array
    {
        $channels = ['in_app'];
        $type = $event['type'];

        if ($type === 'payment_due') {
            $channels[] = 'email';
            $channels[] = 'sms';
        } elseif ($type === 'payoff_milestone') {
            $channels[] = 'email';
        } elseif ($type === 'rate_change') {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * Schedule an event for a future date
     */
    public function scheduleEventForFutureDate(
        string $eventType,
        int $loanId,
        array $eventData,
        string $scheduleDate
    ): array {
        $scheduled = [
            'type' => $eventType,
            'loan_id' => $loanId,
            'data' => $eventData,
            'scheduled_date' => $scheduleDate,
            'status' => 'scheduled',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->scheduledEvents[] = $scheduled;

        return $scheduled;
    }

    /**
     * Execute scheduled events that are due
     */
    public function executeScheduledEvents(): array
    {
        $executed = [];
        $now = new DateTimeImmutable();
        $today = $now->format('Y-m-d');

        foreach ($this->scheduledEvents as &$scheduled) {
            if ($scheduled['status'] === 'scheduled' && $scheduled['scheduled_date'] <= $today) {
                $event = [
                    'type' => $scheduled['type'],
                    'loan_id' => $scheduled['loan_id'],
                    'data' => $scheduled['data'],
                    'timestamp' => date('Y-m-d H:i:s'),
                ];

                $this->events[] = $event;
                $this->notifySubscribers($event);

                $scheduled['status'] = 'executed';
                $executed[] = $event;
            }
        }

        return $executed;
    }

    /**
     * Generate event audit trail for a loan
     */
    public function generateEventAuditTrail(int $loanId): array
    {
        $loanEvents = $this->getEventsForLoan($loanId);

        return [
            'loan_id' => $loanId,
            'events' => array_values($loanEvents),
            'event_count' => count($loanEvents),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate a summary of all events
     */
    public function generateEventSummary(): array
    {
        $eventTypes = [];
        foreach ($this->events as $event) {
            $type = $event['type'];
            $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;
        }

        $loans = array_unique(array_column($this->events, 'loan_id'));

        return [
            'total_events' => count($this->events),
            'event_types' => $eventTypes,
            'unique_loans' => count($loans),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Notify subscribers of an event
     */
    private function notifySubscribers(array $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            if (in_array($event['type'], $subscriber['event_types'])) {
                // Call subscriber handler
                if (is_callable($subscriber['handler'])) {
                    call_user_func($subscriber['handler'], $event);
                }
            }
        }
    }

    /**
     * Export event log to JSON
     */
    public function exportEventLogToJSON(): string
    {
        return json_encode([
            'events' => $this->events,
            'export_date' => date('Y-m-d H:i:s'),
            'total_events' => count($this->events),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Clear events older than a specific date
     */
    public function clearEventsOlderThan(string $date): int
    {
        $before = count($this->events);

        $this->events = array_filter(
            $this->events,
            fn($e) => ($e['timestamp'] ?? $e['date'] ?? $e['due_date'] ?? '') > $date
        );

        return $before - count($this->events);
    }
}
