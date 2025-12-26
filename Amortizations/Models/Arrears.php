<?php

namespace Ksfraser\Amortizations\Models;

use DateTimeImmutable;

/**
 * Arrears Model
 *
 * Represents overdue payment amounts and associated penalties for a loan.
 * Tracks both principal and interest arrears separately to support
 * priority-based payment application.
 *
 * Arrears can accumulate when:
 * - Payment is skipped or missed
 * - Partial payment is made (remainder becomes arrears)
 * - Penalty is assessed
 * - Late fees are added
 *
 * Payment application priority:
 * 1. Penalty/late fees (highest priority - stops compounding)
 * 2. Interest arrears (prevents new interest accrual)
 * 3. Principal arrears (reduces outstanding balance)
 * 4. Current period interest
 * 5. Current period principal (lowest priority)
 *
 * @package Ksfraser\Amortizations\Models
 * @since 2.0
 */
class Arrears
{
    /**
     * @var int|null Unique identifier for this arrears record
     */
    private ?int $id = null;

    /**
     * @var int The loan ID this arrears belongs to
     */
    private int $loanId;

    /**
     * @var float Total amount in arrears (principal + interest)
     */
    private float $totalAmount;

    /**
     * @var float Principal amount overdue
     */
    private float $principalAmount;

    /**
     * @var float Interest amount overdue
     */
    private float $interestAmount;

    /**
     * @var float Penalties and late fees
     */
    private float $penaltyAmount;

    /**
     * @var int Number of days payment is overdue
     */
    private int $daysOverdue;

    /**
     * @var DateTimeImmutable Date when arrears was first created
     */
    private DateTimeImmutable $createdAt;

    /**
     * @var DateTimeImmutable|null Date when arrears was last updated
     */
    private ?DateTimeImmutable $updatedAt;

    /**
     * Create a new arrears record.
     *
     * @param int $loanId The loan ID
     * @param float $principalAmount Amount in principal arrears
     * @param float $interestAmount Amount in interest arrears
     * @param int $daysOverdue Number of days overdue
     *
     * @throws \InvalidArgumentException If amounts are negative
     */
    public function __construct(
        int $loanId,
        float $principalAmount = 0.0,
        float $interestAmount = 0.0,
        int $daysOverdue = 0
    ) {
        if ($principalAmount < 0 || $interestAmount < 0) {
            throw new \InvalidArgumentException("Amounts cannot be negative");
        }

        $this->loanId = $loanId;
        $this->principalAmount = round($principalAmount, 2);
        $this->interestAmount = round($interestAmount, 2);
        $this->penaltyAmount = 0.0;
        $this->daysOverdue = $daysOverdue;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
        $this->totalAmount = $this->calculateTotal();
    }

    /**
     * Get the ID of this arrears record.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the ID (used by repository when loading from database).
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the loan ID this arrears belongs to.
     */
    public function getLoanId(): int
    {
        return $this->loanId;
    }

    /**
     * Get total amount in arrears (all components combined).
     */
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    /**
     * Get principal amount in arrears.
     */
    public function getPrincipalAmount(): float
    {
        return $this->principalAmount;
    }

    /**
     * Get interest amount in arrears.
     */
    public function getInterestAmount(): float
    {
        return $this->interestAmount;
    }

    /**
     * Get penalty and late fees amount.
     */
    public function getPenaltyAmount(): float
    {
        return $this->penaltyAmount;
    }

    /**
     * Get number of days payment is overdue.
     */
    public function getDaysOverdue(): int
    {
        return $this->daysOverdue;
    }

    /**
     * Apply a payment to arrears using priority-based algorithm.
     *
     * Payment application order (highest to lowest priority):
     * 1. Penalty/late fees (stops compounding)
     * 2. Interest arrears (prevents new interest on arrears)
     * 3. Principal arrears (reduces outstanding)
     *
     * Example: payment of $500 with arrears of $200 penalty, $300 interest, $500 principal
     * - $200 to penalty (cleared)
     * - $300 to interest (cleared)
     * - $0 remains
     * Result: $500 principal arrears remains
     *
     * @param float $paymentAmount Amount being applied
     *
     * @return float Remaining payment amount (if payment exceeds arrears)
     *
     * @throws \InvalidArgumentException If payment is negative
     */
    public function applyPayment(float $paymentAmount): float
    {
        if ($paymentAmount < 0) {
            throw new \InvalidArgumentException("Payment cannot be negative");
        }

        $remaining = round($paymentAmount, 2);
        $this->markUpdated();

        // Priority 1: Clear penalty
        if ($remaining > 0 && $this->penaltyAmount > 0) {
            $penaltyPayment = min($remaining, $this->penaltyAmount);
            $this->penaltyAmount -= $penaltyPayment;
            $this->penaltyAmount = round($this->penaltyAmount, 2);
            $remaining -= $penaltyPayment;
            $remaining = round($remaining, 2);
        }

        // Priority 2: Clear interest arrears
        if ($remaining > 0 && $this->interestAmount > 0) {
            $interestPayment = min($remaining, $this->interestAmount);
            $this->interestAmount -= $interestPayment;
            $this->interestAmount = round($this->interestAmount, 2);
            $remaining -= $interestPayment;
            $remaining = round($remaining, 2);
        }

        // Priority 3: Clear principal arrears
        if ($remaining > 0 && $this->principalAmount > 0) {
            $principalPayment = min($remaining, $this->principalAmount);
            $this->principalAmount -= $principalPayment;
            $this->principalAmount = round($this->principalAmount, 2);
            $remaining -= $principalPayment;
            $remaining = round($remaining, 2);
        }

        // Recalculate total
        $this->totalAmount = $this->calculateTotal();

        return $remaining;
    }

    /**
     * Add penalty/late fee to arrears.
     *
     * @param float $amount Penalty amount to add
     *
     * @throws \InvalidArgumentException If amount is negative
     */
    public function addPenalty(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Penalty cannot be negative");
        }

        $this->penaltyAmount += round($amount, 2);
        $this->totalAmount = $this->calculateTotal();
        $this->markUpdated();
    }

    /**
     * Update days overdue count.
     *
     * @param int $days Number of days overdue
     *
     * @throws \InvalidArgumentException If days is negative
     */
    public function setDaysOverdue(int $days): void
    {
        if ($days < 0) {
            throw new \InvalidArgumentException("Days overdue cannot be negative");
        }

        $this->daysOverdue = $days;
        $this->markUpdated();
    }

    /**
     * Check if arrears has been fully cleared.
     */
    public function isCleared(): bool
    {
        return abs($this->totalAmount) < 0.01; // Account for floating point precision
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get last modification timestamp (null if never modified).
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Mark this arrears as modified.
     */
    private function markUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Calculate total arrears amount (sum of all components).
     */
    private function calculateTotal(): float
    {
        return round($this->principalAmount + $this->interestAmount + $this->penaltyAmount, 2);
    }

    /**
     * Get string representation.
     *
     * @return string Format: "$500.00 arrears ($300 principal, $200 interest) - 15 days overdue"
     */
    public function __toString(): string
    {
        return sprintf(
            "$%.2f arrears ($%.2f principal, $%.2f interest, $%.2f penalty) - %d days overdue",
            $this->totalAmount,
            $this->principalAmount,
            $this->interestAmount,
            $this->penaltyAmount,
            $this->daysOverdue
        );
    }
}
