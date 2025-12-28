<?php

namespace Ksfraser\Amortizations\EventHandlers;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\LoanEvent;
use DateTimeImmutable;

/**
 * GracePeriodHandler
 *
 * Handles grace period (initial payment deferral) events.
 * A grace period allows borrowers to defer payments for a specified number of months
 * while interest continues to accrue on the principal.
 *
 * Key behaviors:
 * - Extends loan term by the grace period months
 * - Accrues interest during grace period (added to principal)
 * - No payments due during grace period
 * - First regular payment made after grace period expires
 *
 * Example: $50k loan with 6-month grace period
 * - Grace: Jan 2024 - Jun 2024 (interest accrues)
 * - First payment: Jul 2024
 * - Loan term extended: 60 months → 66 months
 *
 * @implements LoanEventHandler
 * @package Ksfraser\Amortizations\EventHandlers
 * @since 2.0
 */
class GracePeriodHandler implements LoanEventHandler
{
    /**
     * Priority for grace period handler execution.
     * Lower numbers execute first; grace should execute early (before regular payment setup).
     */
    private const PRIORITY = 10;

    /**
     * {@inheritdoc}
     */
    public function supports(LoanEvent $event): bool
    {
        return $event->event_type === 'grace_period' || $event->event_type === 'grace';
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Loan $loan, LoanEvent $event): Loan
    {
        if (!$this->supports($event)) {
            throw new \LogicException("Cannot handle event type: {$event->event_type}");
        }

        if (!isset($event->amount) || $event->amount <= 0) {
            throw new \InvalidArgumentException("Grace period months must be greater than 0");
        }

        $gracePeriodMonths = (int)$event->amount;

        // Apply grace period
        $result = $this->applyGracePeriod($loan, $gracePeriodMonths);

        // Update loan months
        $loan->setMonths($result['months_after_grace']);

        return $loan;
    }

    /**
     * Check if handler supports a given event type (helper for tests).
     *
     * @param string $eventType The event type to check
     * @return bool
     */
    public function supportsEventType(string $eventType): bool
    {
        return $eventType === 'grace_period' || $eventType === 'grace';
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Apply a grace period to a loan.
     *
     * @param Loan $loan The loan to apply grace period to
     * @param int $gracePeriodMonths Number of months for grace period (must be > 0)
     *
     * @return array Event metadata with grace period details
     *
     * @throws \InvalidArgumentException If grace period months is <= 0
     */
    public function applyGracePeriod(Loan $loan, int $gracePeriodMonths): array
    {
        if ($gracePeriodMonths <= 0) {
            throw new \InvalidArgumentException(
                "Grace period months must be greater than 0, got: {$gracePeriodMonths}"
            );
        }

        $principal = $loan->getPrincipal();
        $annualRate = $loan->getAnnualRate();
        $startDate = $loan->getStartDate();
        $currentMonths = $loan->getMonths();

        // Calculate interest accrued during grace period
        $monthlyRate = $annualRate / 12;
        $accruedInterest = 0.0;

        for ($month = 0; $month < $gracePeriodMonths; $month++) {
            $accruedInterest += round($principal * $monthlyRate, 2);
        }
        $accruedInterest = round($accruedInterest, 2);

        // Calculate grace period end date
        $endDate = $startDate->modify("+{$gracePeriodMonths} months");

        // Return event metadata
        return [
            'event_type' => 'grace_period',
            'grace_months' => $gracePeriodMonths,
            'accrued_interest' => $accruedInterest,
            'months_after_grace' => $currentMonths + $gracePeriodMonths,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'principal' => $principal,
            'annual_rate' => $annualRate,
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
}
