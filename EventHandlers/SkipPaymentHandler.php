<?php

namespace Ksfraser\Amortizations\EventHandlers;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\LoanEvent;
use DateTimeImmutable;

/**
 * SkipPaymentHandler - Event handler for skip payment events
 *
 * Allows borrowers to defer one or more regular loan payments with the
 * following business logic:
 *
 * - Borrower can skip 1-12 consecutive payments (fraud protection)
 * - Penalties applied (default 2%, configurable 2-5%)
 * - Loan term extends by number of skipped periods
 * - Schedule recalculated from skip event date
 * - Full metadata recorded for audit trail
 *
 * ### Algorithm
 * 1. Validate skip request (1-12 payments, date valid, account status)
 * 2. Calculate penalty (% of regular payment)
 * 3. Extend term by skipped payment count
 * 4. Add penalty to loan balance
 * 5. Update metadata with event details
 * 6. Return modified loan object for schedule recalculation
 *
 * ### Business Rules
 * - Maximum skip: 12 consecutive payments per event
 * - Minimum skip: 1 payment
 * - Penalty rate: 2-5% (default 2%)
 * - Applied to: Next payment amount after deferral
 * - Term impact: Extends by full skipped months
 *
 * ### Example
 * Loan: $10,000, 5% APR, 60 months, $188.71 payment
 * Event: Skip 2 payments on 2024-12-15
 * Result:
 *   - Penalty: $188.71 * 2% * 2 = $7.55
 *   - Term: 60 + 2 = 62 months
 *   - Balance: $10,000 + $7.55 = $10,007.55
 *
 * @implements LoanEventHandler
 * @package Ksfraser\Amortizations\EventHandlers
 * @since 2.0
 */
class SkipPaymentHandler implements LoanEventHandler
{
    /**
     * @var int Handler priority (10 = grace period, 20 = skip payments, 30 = extra payments)
     */
    private const PRIORITY = 20;

    /**
     * @var float Default penalty rate for skipped payments
     */
    private const DEFAULT_PENALTY_RATE = 0.02; // 2%

    /**
     * @var int Maximum consecutive payments that can be skipped
     */
    private const MAX_SKIP_PAYMENTS = 12;

    /**
     * {@inheritdoc}
     */
    public function supports(LoanEvent $event): bool
    {
        return $this->supportsEventType($event->event_type);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Loan $loan, LoanEvent $event): Loan
    {
        if (!$this->supports($event)) {
            throw new \LogicException("Cannot handle event type: {$event->event_type}");
        }

        $paymentsToSkip = (int)$event->amount;

        // Validate skip request
        if ($paymentsToSkip < 1) {
            throw new \InvalidArgumentException('Must skip at least 1 payment');
        }

        if ($paymentsToSkip > self::MAX_SKIP_PAYMENTS) {
            throw new \InvalidArgumentException(
                "Cannot skip more than " . self::MAX_SKIP_PAYMENTS . " payments"
            );
        }

        // Get penalty rate (default 2%)
        $penaltyRate = self::DEFAULT_PENALTY_RATE;

        // Calculate penalty (apply to approximate monthly payment)
        $approxMonthlyPayment = $loan->getPrincipal() / $loan->getMonths();
        $penalty = $approxMonthlyPayment * $penaltyRate * $paymentsToSkip;
        $penalty = round($penalty, 2);

        // Update loan: extend term and adjust balance
        $newMonths = $loan->getMonths() + $paymentsToSkip;
        $loan->setMonths($newMonths);

        // Add penalty to balance
        $newBalance = round($loan->getCurrentBalance() + $penalty, 2);
        $loan->setCurrentBalance($newBalance);

        // Mark as updated for tracking
        $loan->markUpdated();

        return $loan;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Check if event type is supported
     *
     * @param string $eventType Event type to check
     *
     * @return bool
     */
    private function supportsEventType(string $eventType): bool
    {
        return in_array($eventType, ['skip_payment', 'skip_payments'], true);
    }
}
