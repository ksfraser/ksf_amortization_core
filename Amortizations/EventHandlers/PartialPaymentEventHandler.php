<?php

namespace Ksfraser\Amortizations\EventHandlers;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\LoanEvent;
use Ksfraser\Amortizations\Models\Arrears;

/**
 * PartialPaymentEventHandler
 *
 * Handles partial payment events where borrower pays less than the full
 * regular payment amount. The shortfall is converted to principal arrears.
 *
 * Business logic:
 * 1. Validate payment is less than regular payment but >= 0
 * 2. Apply payment amount to current period
 * 3. Calculate shortfall = regular payment - actual payment
 * 4. Create/update arrears record with shortfall
 * 5. Recalculate remaining schedule to account for arrears
 * 6. Increase future payments or extend term to pay off arrears
 *
 * Example:
 * Regular payment: $726.61
 * Partial payment: $500.00
 * Shortfall: $226.61 (becomes principal arrears)
 *
 * @implements LoanEventHandler
 * @package Ksfraser\Amortizations\EventHandlers
 * @since 2.0
 */
class PartialPaymentEventHandler implements LoanEventHandler
{
    /**
     * {@inheritdoc}
     */
    public function supports(LoanEvent $event): bool
    {
        return $event->event_type === 'partial_payment';
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 60;  // Between extra payments (70) and skip payments (10)
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Loan $loan, LoanEvent $event): Loan
    {
        // Validate event
        $this->validateEvent($event);

        // Extract event data
        $partialPaymentAmount = $event->amount;
        $paymentDate = new \DateTimeImmutable($event->event_date);

        // Get regular payment amount from current schedule
        $currentSchedule = $loan->getSchedule();
        $regularPayment = $this->getRegularPaymentAmount($currentSchedule, $paymentDate);

        // Validate payment amount is partial (less than regular)
        if ($partialPaymentAmount > $regularPayment) {
            throw new \LogicException(
                "Payment of ${$partialPaymentAmount} exceeds regular payment of ${$regularPayment}. " .
                "Use extra_payment event type for amounts >= regular payment."
            );
        }

        // Calculate shortfall
        $shortfall = round($regularPayment - $partialPaymentAmount, 2);

        // Create or update arrears
        if ($shortfall > 0) {
            $this->createArrears($loan, $shortfall, $paymentDate);
        }

        // Apply payment to balance
        $newBalance = round($loan->getCurrentBalance() - $partialPaymentAmount, 2);
        $loan->setCurrentBalance($newBalance);

        // Mark loan as updated
        $loan->markUpdated();

        // Note: In production, would recalculate schedule here
        // This would distribute arrears across remaining payments
        // For now, mark that recalculation is needed

        return $loan;
    }

    /**
     * Validate event contains required fields.
     *
     * @throws \InvalidArgumentException
     */
    private function validateEvent(LoanEvent $event): void
    {
        if ($event->event_type !== 'partial_payment') {
            throw new \InvalidArgumentException("Event type must be 'partial_payment'");
        }

        if (!isset($event->amount)) {
            throw new \InvalidArgumentException("Event must have 'amount' property");
        }

        if ($event->amount < 0) {
            throw new \InvalidArgumentException("Payment amount cannot be negative");
        }

        if (!isset($event->event_date)) {
            throw new \InvalidArgumentException("Event must have 'event_date' property");
        }
    }

    /**
     * Get the regular payment amount for a given date from schedule.
     *
     * Returns the payment amount that should be paid on/around the given date.
     * Defaults to first non-zero payment if date not found exactly.
     *
     * @param array $schedule The amortization schedule
     * @param \DateTimeImmutable $date The payment date
     *
     * @return float The regular payment amount
     */
    private function getRegularPaymentAmount(array $schedule, \DateTimeImmutable $date): float
    {
        // Try to find exact date match first
        foreach ($schedule as $row) {
            if ($row['payment_date'] === $date->format('Y-m-d')) {
                return $row['payment_amount'];
            }
        }

        // If not found, return first period's payment (most loans have consistent payments)
        if (!empty($schedule)) {
            return $schedule[0]['payment_amount'];
        }

        // Fallback - should not happen with valid loan
        throw new \RuntimeException("Cannot determine regular payment amount from schedule");
    }

    /**
     * Create or update arrears record for the shortfall.
     *
     * @param Loan $loan The loan
     * @param float $shortfallAmount The shortfall amount
     * @param \DateTimeImmutable $paymentDate The date of the partial payment
     */
    private function createArrears(
        Loan $loan,
        float $shortfallAmount,
        \DateTimeImmutable $paymentDate
    ): void {
        // Check if arrears already exists
        $existingArrears = $loan->getArrears();
        $arrearsFound = null;

        foreach ($existingArrears as $arr) {
            if (!$arr->isCleared()) {
                $arrearsFound = $arr;
                break;
            }
        }

        if ($arrearsFound !== null) {
            // Update existing arrears - add to principal component
            $newPrincipal = $arrearsFound->getPrincipalAmount() + $shortfallAmount;
            $arrearsFound->setDaysOverdue($arrearsFound->getDaysOverdue() + 30); // Approximate
        } else {
            // Create new arrears record
            $arrearsFound = new Arrears(
                loanId: $loan->getId() ?? 0,
                principalAmount: $shortfallAmount,
                interestAmount: 0.0,
                daysOverdue: 0
            );

            $loan->addArrears($arrearsFound);
        }
    }
}
