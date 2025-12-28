<?php

namespace Ksfraser\Amortizations\EventHandlers;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\LoanEvent;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTimeImmutable;

/**
 * ExtraPaymentHandler - Event handler for extra payment events
 *
 * Allows borrowers to make extra payments beyond the regular scheduled payment
 * with the following features:
 *
 * - Extra payments reduce principal directly
 * - Two strategies available:
 *   1. reduce_term (default): Shortens loan term, keeps payment same
 *   2. reduce_payment: Extends term, reduces monthly payment
 * - Calculates interest savings from early payoff
 * - Supports multiple extra payments
 * - Full audit trail via event metadata
 *
 * ### Algorithm (reduce_term strategy)
 * 1. Validate extra payment amount (0 < amount <= remaining balance)
 * 2. Reduce current balance by extra payment
 * 3. Calculate new months to payoff with reduced balance
 * 4. Record event metadata with interest savings
 *
 * ### Algorithm (reduce_payment strategy)
 * 1. Validate extra payment amount
 * 2. Reduce current balance by extra payment
 * 3. Keep term same, recalculate payment with reduced principal
 * 4. Record metadata showing lower payment amount
 *
 * ### Business Rules
 * - Extra payment must be > $0
 * - Extra payment cannot exceed remaining balance
 * - Default strategy: reduce_term (shorter payoff)
 * - Reduces interest paid over life of loan
 * - Can be applied at any point in loan lifecycle
 *
 * ### Example
 * Loan: $10,000, 5% APR, 60 months, $188.71 payment
 * Event: Extra payment of $2,000 (reduce_term strategy)
 * Result:
 *   - New balance: $8,000
 *   - New months: ~47 (down from 60)
 *   - Interest saved: ~$180
 *
 * @implements LoanEventHandler
 * @package Ksfraser\Amortizations\EventHandlers
 * @since 2.0
 */
class ExtraPaymentHandler implements LoanEventHandler
{
    /**
     * @var int Handler priority (20 = skip payments, 30 = extra payments)
     */
    private const PRIORITY = 30;

    /**
     * @var string Default strategy for extra payments
     */
    private const DEFAULT_STRATEGY = 'reduce_term';

    /**
     * @var DecimalCalculator For precise calculations
     */
    private $calc;

    public function __construct()
    {
        $this->calc = new DecimalCalculator();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(LoanEvent $event): bool
    {
        return $event->event_type === 'extra_payment';
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Loan $loan, LoanEvent $event): Loan
    {
        if (!$this->supports($event)) {
            throw new \LogicException("Cannot handle event type: {$event->event_type}");
        }

        $extraPaymentAmount = (float)$event->amount;

        // Validate extra payment
        if ($extraPaymentAmount <= 0) {
            throw new \InvalidArgumentException(
                'Extra payment must be greater than $0.00'
            );
        }

        $remainingBalance = $loan->getCurrentBalance();
        if ($extraPaymentAmount > $remainingBalance) {
            throw new \InvalidArgumentException(
                'Extra payment cannot exceed remaining balance of $' .
                number_format($remainingBalance, 2)
            );
        }

        // Get strategy from notes field
        $strategy = $this->getStrategy($event->notes ?? '');

        // Apply extra payment (reduces balance immediately)
        $newBalance = $this->calc->asFloat(
            $this->calc->subtract($remainingBalance, $extraPaymentAmount, 2),
            2
        );
        $loan->setCurrentBalance($newBalance);

        // Apply selected strategy
        if ($strategy === 'reduce_payment') {
            $this->applyReducePaymentStrategy($loan, $extraPaymentAmount, $event);
        } else {
            // Default: reduce_term
            $this->applyReduceTermStrategy($loan, $extraPaymentAmount, $event);
        }

        // Mark loan as updated
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
     * Apply reduce_term strategy
     *
     * Shortens the loan term by applying extra payment to principal.
     * Monthly payment stays same, loan paid off sooner.
     *
     * @param Loan $loan
     * @param float $extraPaymentAmount
     * @param LoanEvent $event
     *
     * @return void
     */
    private function applyReduceTermStrategy(Loan $loan, float $extraPaymentAmount, LoanEvent $event): void
    {
        $originalMonths = $loan->getMonths();
        $currentBalance = $loan->getCurrentBalance();
        $annualRate = $loan->getAnnualRate();
        $monthlyRate = $this->calc->divide($annualRate, 12, 6);

        // Estimate new months to payoff
        // Using approximation: extra_payment reduces term proportionally
        $principal = $loan->getPrincipal();
        $termsReduction = round(($extraPaymentAmount / $principal) * $originalMonths);
        $newMonths = max(1, $originalMonths - $termsReduction);

        $loan->setMonths($newMonths);

        // Calculate interest savings
        $interestSavings = $this->calculateInterestSavings(
            $currentBalance,
            $monthlyRate,
            $originalMonths,
            $newMonths
        );

        // Store event info
        $this->storeEventInfo($loan, $event, $extraPaymentAmount, $newMonths, $interestSavings);
    }

    /**
     * Apply reduce_payment strategy
     *
     * Reduces the monthly payment amount while keeping term the same.
     * Lower monthly payment, loan payoff date unchanged.
     *
     * @param Loan $loan
     * @param float $extraPaymentAmount
     * @param LoanEvent $event
     *
     * @return void
     */
    private function applyReducePaymentStrategy(Loan $loan, float $extraPaymentAmount, LoanEvent $event): void
    {
        $months = $loan->getMonths();
        $currentBalance = $loan->getCurrentBalance();
        $annualRate = $loan->getAnnualRate();
        $monthlyRate = $this->calc->divide($annualRate, 12, 6);

        // Calculate new monthly payment with reduced balance
        // Using same formula but with reduced principal
        $oneRate = $this->calc->add(1, $monthlyRate, 10);
        $compoundFactor = $this->calc->power($oneRate, $months, 10);
        $numerator = $this->calc->multiply($monthlyRate, $compoundFactor, 10);
        $denominator = $this->calc->subtract($compoundFactor, 1, 10);
        $newPayment = $this->calc->divide(
            $this->calc->multiply($currentBalance, $numerator, 10),
            $denominator,
            10
        );

        $paymentReduction = $this->calc->asFloat(
            $this->calc->subtract(
                $this->estimateCurrentPayment($loan),
                $newPayment,
                2
            ),
            2
        );

        // Calculate interest savings
        $originalMonths = $loan->getMonths();
        $interestSavings = $this->calculateInterestSavings(
            $currentBalance,
            $monthlyRate,
            $originalMonths,
            $originalMonths
        );

        // Store event info
        $this->storeEventInfo($loan, $event, $extraPaymentAmount, $originalMonths, $interestSavings);
    }

    /**
     * Extract strategy from event notes
     *
     * @param string $notes Notes field potentially containing strategy
     *
     * @return string Strategy name (reduce_term or reduce_payment)
     */
    private function getStrategy(string $notes): string
    {
        if (strpos($notes, 'reduce_payment') !== false) {
            return 'reduce_payment';
        }
        return self::DEFAULT_STRATEGY;
    }

    /**
     * Calculate interest savings from early payoff
     *
     * @param float $balance Current balance after payment
     * @param string $monthlyRate Monthly interest rate (decimal string)
     * @param int $originalMonths Original number of months
     * @param int $newMonths New number of months
     *
     * @return float Interest savings amount
     */
    private function calculateInterestSavings($balance, $monthlyRate, int $originalMonths, int $newMonths): float
    {
        // Simplified: interest saved = balance * rate * (months_saved)
        $monthsSaved = $originalMonths - $newMonths;
        $interestSavings = (float)$this->calc->multiply($balance, $monthlyRate, 2);
        $interestSavings = (float)$this->calc->multiply($interestSavings, $monthsSaved, 2);

        return $this->calc->asFloat($interestSavings, 2);
    }

    /**
     * Estimate current monthly payment
     *
     * @param Loan $loan
     *
     * @return string Estimated payment as decimal string
     */
    private function estimateCurrentPayment(Loan $loan): string
    {
        $principal = $loan->getPrincipal();
        $months = $loan->getMonths();
        $annualRate = $loan->getAnnualRate();

        $monthlyRate = $this->calc->divide($annualRate, 1200, 10);
        $oneRate = $this->calc->add(1, $monthlyRate, 10);
        $compoundFactor = $this->calc->power($oneRate, $months, 10);
        $numerator = $this->calc->multiply($monthlyRate, $compoundFactor, 10);
        $denominator = $this->calc->subtract($compoundFactor, 1, 10);

        return $this->calc->divide(
            $this->calc->multiply($principal, $numerator, 10),
            $denominator,
            10
        );
    }

    /**
     * Store event metadata for audit trail
     *
     * @param Loan $loan
     * @param LoanEvent $event
     * @param float $extraPaymentAmount
     * @param int $resultingMonths
     * @param float $interestSavings
     *
     * @return void
     */
    private function storeEventInfo(
        Loan $loan,
        LoanEvent $event,
        float $extraPaymentAmount,
        int $resultingMonths,
        float $interestSavings
    ): void
    {
        // Note: Currently storing in event notes, future could use separate audit table
        $eventInfo = [
            'extra_payment' => $extraPaymentAmount,
            'resulting_months' => $resultingMonths,
            'interest_savings' => $interestSavings,
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        // Append to notes (or could implement proper metadata table)
        $event->notes = json_encode($eventInfo);
    }
}
