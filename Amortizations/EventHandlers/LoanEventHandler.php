<?php

namespace Ksfraser\Amortizations\EventHandlers;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\LoanEvent;

/**
 * LoanEventHandler Interface
 *
 * Defines the contract for handling loan-related events that require
 * recalculation or state modifications. Implements Observer Pattern to
 * decouple event triggering from event handling logic.
 *
 * Event types handled:
 * - Extra payment applied
 * - Partial payment received
 * - Rate change effective
 * - Skip payment requested
 * - Penalty assessed
 * - Payment adjustment needed
 *
 * Handler responsibilities:
 * 1. Validate event data
 * 2. Update loan state/schedule
 * 3. Trigger recalculation if needed
 * 4. Persist changes
 * 5. Return updated loan with recalculated schedule
 *
 * @package Ksfraser\Amortizations\EventHandlers
 * @since 2.0
 */
interface LoanEventHandler
{
    /**
     * Handle a loan event and return updated loan state.
     *
     * Event processing workflow:
     * 1. Validate event payload against expected schema
     * 2. Update relevant loan properties (balance, dates, rates, etc.)
     * 3. Determine if recalculation needed
     * 4. If recalculation needed:
     *    a. Call appropriate LoanCalculationStrategy
     *    b. Regenerate schedule from current date forward
     *    c. Update all affected schedule rows
     * 5. Persist to repository
     * 6. Return updated loan with new schedule
     *
     * The handler should maintain referential integrity and handle
     * partial updates where only subset of periods may be affected.
     *
     * @param Loan $loan The loan to process event for
     * @param LoanEvent $event The event containing payload and type
     *
     * @return Loan Updated loan with recalculated schedule if needed
     *
     * @throws \InvalidArgumentException If event data is invalid
     * @throws \RuntimeException If recalculation fails
     * @throws \LogicException If event cannot be applied to loan
     */
    public function handle(Loan $loan, LoanEvent $event): Loan;

    /**
     * Get priority for this handler.
     *
     * Used when multiple handlers process same event.
     * Higher number = higher priority = executes first.
     *
     * Priority guidelines:
     * - Arrears handling: 100 (highest - must process before payments)
     * - Penalties: 90
     * - Rate changes: 80
     * - Extra payments: 70
     * - Partial payments: 60
     * - Skip payments: 50
     * - General updates: 10 (lowest)
     *
     * @return int Priority value (0-100)
     */
    public function getPriority(): int;

    /**
     * Determine if this handler can process a given event.
     *
     * Used by EventDispatcher to route events to appropriate handlers.
     *
     * Examples:
     * - PartialPaymentEventHandler: returns true for 'partial_payment' events
     * - RateChangeEventHandler: returns true for 'rate_change' events
     * - ArrearsEventHandler: returns true for 'arrears_*' events
     *
     * @param LoanEvent $event The event to evaluate
     *
     * @return bool True if this handler can process the event
     */
    public function supports(LoanEvent $event): bool;
}
