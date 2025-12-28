<?php

namespace Ksfraser\Amortizations\Strategies;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTimeImmutable;

/**
 * BalloonPaymentStrategy
 *
 * Implements balloon payment amortization where a large final payment
 * is due at the end of the loan term.
 *
 * Common use cases:
 * - Vehicle leases ($30k vehicle, $12k balloon final payment)
 * - Some mortgages with large final balloon
 * - Equipment financing with residual value
 *
 * Algorithm:
 * 1. Calculate effective principal = original principal - balloon amount
 * 2. Use standard amortization formula for effective principal
 * 3. Generate schedule with regular payments based on effective principal
 * 4. Add balloon amount to final payment
 *
 * Formula:
 * Monthly Payment = (P - B) × [r(1+r)^n] / [(1+r)^n - 1]
 * Where:
 *   P = original principal amount
 *   B = balloon amount (final payment)
 *   r = monthly interest rate (annual rate / 12)
 *   n = number of periods (months)
 *
 * Example: $50,000 car lease, 5% annual rate, 60 months, $12,000 balloon
 * Monthly Payment = ($50,000 - $12,000) × [0.004167(1.004167)^60] / [(1.004167)^60 - 1]
 *                 = $726.61/month
 *
 * Uses DecimalCalculator for arbitrary precision arithmetic to eliminate
 * floating-point errors that accumulate over many payment periods.
 *
 * @implements LoanCalculationStrategy
 * @package Ksfraser\Amortizations\Strategies
 * @since 2.0
 */
class BalloonPaymentStrategy implements LoanCalculationStrategy
{
    /**
     * @var DecimalCalculator Arbitrary precision calculator
     */
    private $calc;

    /**
     * Initialize with DecimalCalculator
     */
    public function __construct()
    {
        $this->calc = new DecimalCalculator();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Loan $loan): bool
    {
        return $loan->hasBalloonPayment() === true;
    }

    /**
     * {@inheritdoc}
     */
    public function calculatePayment(Loan $loan, ?int $periodNumber = null): float
    {
        if (!$this->supports($loan)) {
            throw new \InvalidArgumentException('Loan does not have a balloon payment configured');
        }

        $balloonAmount = $loan->getBalloonAmount();
        $principal = $loan->getPrincipal();
        $annualRate = $loan->getAnnualRate();
        $months = $loan->getMonths();

        // Validate balloon is valid
        if ($balloonAmount >= $principal) {
            throw new \InvalidArgumentException(
                "Balloon amount ({$balloonAmount}) cannot be >= principal ({$principal})"
            );
        }

        // Special case: 1-month loan, pay everything at once
        if ($months === 1) {
            $interest = $this->calc->multiply($principal, $this->calc->divide($annualRate, 12, 6));
            return $this->calc->asFloat($this->calc->add($principal, $interest, 2), 2);
        }

        // Calculate effective principal (what we're financing minus the balloon)
        $effectivePrincipal = $this->calc->subtract($principal, $balloonAmount, 6);

        // Handle edge case: 0% interest
        if ($annualRate == 0) {
            return $this->calc->asFloat($this->calc->divide($effectivePrincipal, $months, 2), 2);
        }

        // Convert annual rate to monthly rate with high precision
        $monthlyRate = $this->calc->divide($annualRate, 1200, 10); // Divide by 1200 (12 * 100)

        // Standard amortization formula: Payment = PV × [r(1+r)^n] / [(1+r)^n - 1]
        $oneRate = $this->calc->add(1, $monthlyRate, 10);
        $compoundFactor = $this->calc->power($oneRate, $months, 10);
        $numerator = $this->calc->multiply($monthlyRate, $compoundFactor, 10);
        $denominator = $this->calc->subtract($compoundFactor, 1, 10);
        $payment = $this->calc->divide(
            $this->calc->multiply($effectivePrincipal, $numerator, 10),
            $denominator,
            10
        );

        return $this->calc->asFloat($payment, 2);
    }

    /**
     * {@inheritdoc}
     */
    public function calculateSchedule(Loan $loan): array
    {
        if (!$this->supports($loan)) {
            throw new \InvalidArgumentException('Loan does not have a balloon payment configured');
        }

        $monthlyPayment = $this->calc->divide(
            $this->calculatePayment($loan),
            1,
            10
        ); // Convert to decimal string with high precision
        $annualRate = $loan->getAnnualRate();
        $monthlyRate = $this->calc->divide($annualRate, 1200, 10);
        $months = $loan->getMonths();
        $balloonAmount = $loan->getBalloonAmount();
        $currentDate = $loan->getStartDate();
        $balance = $this->calc->divide($loan->getPrincipal(), 1, 10);

        $schedule = [];

        for ($period = 1; $period <= $months; $period++) {
            // Calculate interest for this period
            $interest = $this->calc->multiply($balance, $monthlyRate, 2);

            // Determine principal payment for this period
            if ($period === $months) {
                // Final period: principal = remaining balance
                $principal = $balance;
            } else {
                // Regular period: principal = payment - interest
                $principal = $this->calc->subtract($monthlyPayment, $interest, 2);
            }

            // Update balance
            $balance = $this->calc->subtract($balance, $principal, 2);

            // Ensure final balance is $0.00
            if ($period === $months) {
                $balance = '0.00';
            }

            // Determine payment amount for this period
            if ($period === $months) {
                // Final period: pay the remaining balance + interest
                $paymentAmount = $this->calc->add($interest, $principal, 2);
            } else {
                $paymentAmount = $this->calc->add($principal, $interest, 2);
            }

            $schedule[] = [
                'payment_number' => $period,
                'payment_date' => $currentDate->format('Y-m-d'),
                'payment_amount' => $this->calc->asFloat($paymentAmount, 2),
                'principal' => $this->calc->asFloat($principal, 2),
                'interest' => $this->calc->asFloat($interest, 2),
                'balance' => $this->calc->asFloat($balance, 2),
                'balloon_amount' => $period === $months ? $balloonAmount : null,
            ];

            // Move to next month
            $currentDate = $currentDate->modify('+1 month');
        }

        return $schedule;
    }
}
