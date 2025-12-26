<?php

namespace Ksfraser\Amortizations\Models;

use DateTimeImmutable;

/**
 * Loan Model
 *
 * Core loan aggregate representing a complete loan with all its properties,
 * calculated values, and schedule.
 *
 * A loan encapsulates:
 * - Core terms (principal, rate, months, dates)
 * - Special features (balloon, variable rates, grace periods)
 * - Derived values (balance, payments remaining)
 * - Relationships (rates, arrears, schedule)
 *
 * Example:
 * ```
 * $loan = new Loan();
 * $loan->setPrincipal(50000);
 * $loan->setAnnualRate(0.05);
 * $loan->setMonths(60);
 * $loan->setBalloonAmount(12000);
 * ```
 *
 * @package Ksfraser\Amortizations\Models
 * @since 2.0
 */
class Loan
{
    /**
     * @var int|null Unique identifier for this loan
     */
    private ?int $id = null;

    /**
     * @var float The original loan principal amount
     */
    private float $principal = 0.0;

    /**
     * @var float Annual interest rate as decimal (5.5% = 0.055)
     */
    private float $annualRate = 0.0;

    /**
     * @var int Number of payment periods (months)
     */
    private int $months = 0;

    /**
     * @var DateTimeImmutable When the loan begins
     */
    private DateTimeImmutable $startDate;

    /**
     * @var float|null Balloon payment amount (if applicable)
     */
    private ?float $balloonAmount = null;

    /**
     * @var RatePeriod[] Array of rate periods for variable rate loans
     */
    private array $ratePeriods = [];

    /**
     * @var Arrears[] Array of arrears records for this loan
     */
    private array $arrears = [];

    /**
     * @var array The complete amortization schedule
     */
    private array $schedule = [];

    /**
     * @var float Current outstanding balance
     */
    private float $currentBalance = 0.0;

    /**
     * @var int Number of payments made so far
     */
    private int $paymentsMade = 0;

    /**
     * @var DateTimeImmutable|null Date when loan was created
     */
    private ?DateTimeImmutable $createdAt = null;

    /**
     * @var DateTimeImmutable|null Date when loan was last updated
     */
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * Create a new loan instance.
     */
    public function __construct()
    {
        $this->startDate = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
    }

    // ===== Getters and Setters =====

    /**
     * Get the loan ID.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the loan ID (used when loading from repository).
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the principal amount.
     */
    public function getPrincipal(): float
    {
        return $this->principal;
    }

    /**
     * Set the principal amount.
     */
    public function setPrincipal(float $principal): self
    {
        if ($principal <= 0) {
            throw new \InvalidArgumentException("Principal must be greater than 0");
        }
        $this->principal = round($principal, 2);
        $this->currentBalance = $this->principal;
        return $this;
    }

    /**
     * Get the annual interest rate.
     */
    public function getAnnualRate(): float
    {
        return $this->annualRate;
    }

    /**
     * Set the annual interest rate.
     */
    public function setAnnualRate(float $rate): self
    {
        if ($rate < 0 || $rate > 1) {
            throw new \InvalidArgumentException("Rate must be between 0 and 1");
        }
        $this->annualRate = $rate;
        return $this;
    }

    /**
     * Get the number of months.
     */
    public function getMonths(): int
    {
        return $this->months;
    }

    /**
     * Set the number of months.
     */
    public function setMonths(int $months): self
    {
        if ($months <= 0) {
            throw new \InvalidArgumentException("Months must be greater than 0");
        }
        $this->months = $months;
        return $this;
    }

    /**
     * Get the start date.
     */
    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Set the start date.
     */
    public function setStartDate(DateTimeImmutable $date): self
    {
        $this->startDate = $date;
        return $this;
    }

    /**
     * Get the balloon payment amount (null if no balloon).
     */
    public function getBalloonAmount(): ?float
    {
        return $this->balloonAmount;
    }

    /**
     * Set the balloon payment amount.
     */
    public function setBalloonAmount(?float $amount): self
    {
        if ($amount !== null && $amount < 0) {
            throw new \InvalidArgumentException("Balloon amount cannot be negative");
        }
        $this->balloonAmount = $amount !== null ? round($amount, 2) : null;
        return $this;
    }

    /**
     * Check if this loan has a balloon payment.
     */
    public function hasBalloonPayment(): bool
    {
        return $this->balloonAmount !== null && $this->balloonAmount > 0;
    }

    /**
     * Get all rate periods.
     *
     * @return RatePeriod[]
     */
    public function getRatePeriods(): array
    {
        return $this->ratePeriods;
    }

    /**
     * Add a rate period to this loan.
     */
    public function addRatePeriod(RatePeriod $period): self
    {
        if ($period->getLoanId() !== $this->id && $this->id !== null) {
            throw new \InvalidArgumentException("Rate period belongs to different loan");
        }
        $this->ratePeriods[] = $period;
        return $this;
    }

    /**
     * Get the interest rate applicable on a specific date.
     *
     * @param DateTimeImmutable $date The date to check
     *
     * @return float The applicable rate (or $annualRate if no rate periods defined)
     */
    public function getRateForDate(DateTimeImmutable $date): float
    {
        foreach ($this->ratePeriods as $period) {
            if ($period->isActive($date)) {
                return $period->getRate();
            }
        }
        return $this->annualRate;
    }

    /**
     * Get all arrears records.
     *
     * @return Arrears[]
     */
    public function getArrears(): array
    {
        return $this->arrears;
    }

    /**
     * Add an arrears record to this loan.
     */
    public function addArrears(Arrears $arrears): self
    {
        if ($arrears->getLoanId() !== $this->id && $this->id !== null) {
            throw new \InvalidArgumentException("Arrears belongs to different loan");
        }
        $this->arrears[] = $arrears;
        return $this;
    }

    /**
     * Get current total arrears amount.
     */
    public function getTotalArrears(): float
    {
        $total = 0.0;
        foreach ($this->arrears as $arr) {
            $total += $arr->getTotalAmount();
        }
        return round($total, 2);
    }

    /**
     * Get the amortization schedule.
     *
     * @return array Array of schedule rows
     */
    public function getSchedule(): array
    {
        return $this->schedule;
    }

    /**
     * Set the amortization schedule.
     */
    public function setSchedule(array $schedule): self
    {
        $this->schedule = $schedule;
        return $this;
    }

    /**
     * Get current outstanding balance.
     */
    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }

    /**
     * Set current balance (used by repository).
     */
    public function setCurrentBalance(float $balance): self
    {
        $this->currentBalance = round($balance, 2);
        return $this;
    }

    /**
     * Get number of payments made.
     */
    public function getPaymentsMade(): int
    {
        return $this->paymentsMade;
    }

    /**
     * Set payments made count.
     */
    public function setPaymentsMade(int $count): self
    {
        $this->paymentsMade = max(0, $count);
        return $this;
    }

    /**
     * Get number of remaining payments.
     */
    public function getPaymentsRemaining(): int
    {
        return max(0, $this->months - $this->paymentsMade);
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get last modification timestamp.
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Mark this loan as updated.
     */
    public function markUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Get string representation of loan.
     *
     * @return string Format: "$50,000.00 @ 5.50% for 60 months (with $12,000 balloon if applicable)"
     */
    public function __toString(): string
    {
        $balloon = $this->hasBalloonPayment() ? " (with ${$this->balloonAmount} balloon)" : '';
        return sprintf(
            "$%.2f @ %.2f%% for %d months%s",
            $this->principal,
            $this->annualRate * 100,
            $this->months,
            $balloon
        );
    }
}
