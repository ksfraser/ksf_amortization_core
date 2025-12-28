<?php

namespace Ksfraser\Amortizations\Models;

use DateTime;
use DateTimeImmutable;

/**
 * RatePeriod Model
 *
 * Represents a period during which a specific interest rate applies to a loan.
 * Supports variable-rate loans by defining discrete periods with different rates.
 *
 * Example loan with rate changes:
 * - Period 1 (Jan 2024 - Jun 2024): 5.5%
 * - Period 2 (Jul 2024 - Dec 2024): 6.0%
 * - Period 3 (Jan 2025 onwards):    6.5%
 *
 * Each rate period triggers schedule recalculation for affected periods.
 *
 * @package Ksfraser\Amortizations\Models
 * @since 2.0
 */
class RatePeriod
{
    /**
     * @var int|null Unique identifier for this rate period
     */
    private ?int $id = null;

    /**
     * @var int The loan this rate period belongs to
     */
    private int $loanId;

    /**
     * @var float Interest rate for this period (as decimal, e.g., 0.055 for 5.5%)
     */
    private float $rate;

    /**
     * @var DateTimeImmutable Start date of this rate period (inclusive)
     */
    private DateTimeImmutable $startDate;

    /**
     * @var DateTimeImmutable|null End date of this rate period (inclusive), null if ongoing
     */
    private ?DateTimeImmutable $endDate;

    /**
     * @var DateTimeImmutable Date when this rate period was created
     */
    private DateTimeImmutable $createdAt;

    /**
     * @var DateTimeImmutable|null Date when this rate period was modified
     */
    private ?DateTimeImmutable $updatedAt;

    /**
     * Create a new rate period.
     *
     * @param int $loanId The loan ID this period applies to
     * @param float $rate Interest rate as decimal (5.5% = 0.055)
     * @param DateTimeImmutable $startDate When this rate becomes effective
     * @param DateTimeImmutable|null $endDate When this rate ends (null = ongoing)
     *
     * @throws \InvalidArgumentException If rate is negative or invalid
     */
    public function __construct(
        int $loanId,
        float $rate,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate = null
    ) {
        if ($rate < 0 || $rate > 1) {
            throw new \InvalidArgumentException("Rate must be between 0 and 1, got: {$rate}");
        }

        if ($endDate !== null && $startDate > $endDate) {
            throw new \InvalidArgumentException("Start date cannot be after end date");
        }

        $this->loanId = $loanId;
        $this->rate = $rate;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

    /**
     * Get the ID of this rate period.
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
     * Get the loan ID this period applies to.
     */
    public function getLoanId(): int
    {
        return $this->loanId;
    }

    /**
     * Get the interest rate for this period.
     */
    public function getRate(): float
    {
        return $this->rate;
    }

    /**
     * Get the start date of this rate period.
     */
    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Get the end date of this rate period (null if ongoing).
     */
    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * Check if this rate period is active on a given date.
     *
     * Active means the date falls within [startDate, endDate].
     * If endDate is null, any date >= startDate is considered active.
     *
     * @param DateTimeImmutable $date The date to check
     *
     * @return bool True if this rate period applies on the given date
     */
    public function isActive(DateTimeImmutable $date): bool
    {
        if ($date < $this->startDate) {
            return false;
        }

        if ($this->endDate === null) {
            return true;
        }

        return $date <= $this->endDate;
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
     * Mark this period as modified.
     */
    public function markUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Get string representation of this rate period.
     *
     * @return string Format: "5.50% from 2024-01-01 to 2024-12-31"
     */
    public function __toString(): string
    {
        $ratePercent = round($this->rate * 100, 2);
        $endStr = $this->endDate ? $this->endDate->format('Y-m-d') : 'ongoing';
        return "{$ratePercent}% from {$this->startDate->format('Y-m-d')} to {$endStr}";
    }
}
