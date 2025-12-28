<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\AmortizationModel;
use InvalidArgumentException;
use DateInterval;
use DateTime;

class AdvancedAmortizationService
{
    /**
     * @var AmortizationModel
     */
    private $amortizationModel;
    /**
     * @var CacheManager
     */
    private $cacheManager;

    public function __construct(
        AmortizationModel $amortizationModel,
        ?CacheManager $cacheManager = null
    ) {
        $this->amortizationModel = $amortizationModel;
        $this->cacheManager = $cacheManager ?? new CacheManager();
    }

    /**
     * Generate amortization schedule with balloon payment
     * Balloon: lump sum due at end of term
     */
    public function generateBalloonPaymentSchedule(
        float $principal,
        float $rate,
        int $months,
        float $balloonPayment,
        string $paymentFrequency = 'monthly'
    ): array {
        if ($balloonPayment < 0) {
            throw new InvalidArgumentException('Balloon payment must be non-negative');
        }
        if ($balloonPayment >= $principal) {
            throw new InvalidArgumentException('Balloon payment cannot exceed principal');
        }

        $cacheKey = "balloon_{$principal}_{$rate}_{$months}_{$balloonPayment}";
        if ($cached = $this->cacheManager->get($cacheKey)) {
            return $cached;
        }

        $amortizable = $principal - $balloonPayment;
        $monthlyRate = $rate / 100 / 12;
        $payment = $this->calculatePayment($amortizable, $monthlyRate, $months);
        
        $schedule = [];
        $balance = $amortizable;

        for ($i = 1; $i <= $months; $i++) {
            $interest = $balance * $monthlyRate;
            $principalPayment = $payment - $interest;
            $balance -= $principalPayment;

            $payment_record = [
                'payment_number' => $i,
                'payment' => round($payment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interest, 2),
                'balance' => round(max(0, $balance), 2),
            ];

            // Add balloon payment to final payment
            if ($i === $months) {
                $payment_record['payment'] = round($payment + $balloonPayment, 2);
                $payment_record['balloon_payment'] = $balloonPayment;
            }

            $schedule[] = $payment_record;
        }

        $this->cacheManager->set($cacheKey, $schedule, 3600);
        return $schedule;
    }

    /**
     * Generate schedule with variable interest rates
     * Rates change at specified intervals
     */
    public function generateVariableRateSchedule(
        float $principal,
        array $rateSchedule,
        int $monthsPerTerm,
        string $paymentFrequency = 'monthly'
    ): array {
        if (empty($rateSchedule)) {
            throw new InvalidArgumentException('Rate schedule cannot be empty');
        }
        if ($monthsPerTerm < 1) {
            throw new InvalidArgumentException('Months per term must be positive');
        }

        $cacheKey = 'variable_' . md5(json_encode([
            'principal' => $principal,
            'schedule' => $rateSchedule,
            'months_per_term' => $monthsPerTerm
        ]));

        if ($cached = $this->cacheManager->get($cacheKey)) {
            return $cached;
        }

        $schedule = [];
        $balance = $principal;
        $paymentNumber = 0;
        $rateIndex = 0;
        $monthsInTerm = 0;
        $currentRate = $rateSchedule[0] ?? 0;

        while ($balance > 0 && count($schedule) < 600) {
            // Switch rate if term complete
            if ($monthsInTerm >= $monthsPerTerm && isset($rateSchedule[$rateIndex + 1])) {
                $rateIndex++;
                $currentRate = $rateSchedule[$rateIndex];
                $monthsInTerm = 0;
            }

            $paymentNumber++;
            $monthlyRate = $currentRate / 100 / 12;
            $interest = $balance * $monthlyRate;
            $payment = $this->calculatePayment($balance, $monthlyRate, 360 - $paymentNumber);

            if ($payment > $balance + $interest) {
                $payment = $balance + $interest;
            }

            $principal = $payment - $interest;
            $balance = max(0, $balance - $principal);

            $schedule[] = [
                'payment_number' => $paymentNumber,
                'payment' => round($payment, 2),
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'balance' => round($balance, 2),
                'rate' => $currentRate,
                'term_number' => $rateIndex + 1,
                'months_in_term' => $monthsInTerm + 1,
            ];

            $monthsInTerm++;
        }

        $this->cacheManager->set($cacheKey, $schedule, 3600);
        return $schedule;
    }

    /**
     * Handle prepayment scenarios - lump sum payment towards principal
     */
    public function applyPrepayment(
        array $originalSchedule,
        int $paymentNumber,
        float $prepaymentAmount,
        bool $recalculate = true
    ): array {
        if ($paymentNumber < 1 || $paymentNumber > count($originalSchedule)) {
            throw new InvalidArgumentException('Invalid payment number');
        }
        if ($prepaymentAmount <= 0) {
            throw new InvalidArgumentException('Prepayment amount must be positive');
        }

        $schedule = array_slice($originalSchedule, 0, $paymentNumber);
        $currentPayment = $schedule[$paymentNumber - 1];
        $remainingBalance = $currentPayment['balance'] - $prepaymentAmount;

        if ($remainingBalance < 0) {
            throw new InvalidArgumentException('Prepayment exceeds remaining balance');
        }

        $schedule[$paymentNumber - 1]['prepayment'] = $prepaymentAmount;
        $schedule[$paymentNumber - 1]['balance'] -= $prepaymentAmount;
        $schedule[$paymentNumber - 1]['balance'] = round($schedule[$paymentNumber - 1]['balance'], 2);

        if ($recalculate && $remainingBalance > 0) {
            $lastPayment = $schedule[$paymentNumber - 1];
            $monthlyRate = ($lastPayment['rate'] ?? 5.0) / 100 / 12;
            $remainingMonths = count($originalSchedule) - $paymentNumber;

            // Recalculate remaining payments
            for ($i = $paymentNumber; $i < count($originalSchedule); $i++) {
                if ($remainingBalance <= 0) break;

                $interest = $remainingBalance * $monthlyRate;
                $payment = min(
                    $this->calculatePayment($remainingBalance, $monthlyRate, $remainingMonths - ($i - $paymentNumber)),
                    $remainingBalance + $interest
                );

                $principal = $payment - $interest;
                $remainingBalance -= $principal;

                $schedule[] = [
                    'payment_number' => $i + 1,
                    'payment' => round($payment, 2),
                    'principal' => round($principal, 2),
                    'interest' => round($interest, 2),
                    'balance' => round(max(0, $remainingBalance), 2),
                    'rate' => $lastPayment['rate'] ?? 5.0,
                    'prepayment_applied' => true,
                ];

                $remainingMonths--;
            }
        }

        return $schedule;
    }

    /**
     * Handle skip payment scenarios (typically for hardship)
     * Interest accrues during skipped month
     */
    public function applySkipPayment(
        array $originalSchedule,
        int $paymentNumber,
        bool $capitalizeInterest = true
    ): array {
        if ($paymentNumber < 1 || $paymentNumber > count($originalSchedule)) {
            throw new InvalidArgumentException('Invalid payment number');
        }

        $schedule = array_slice($originalSchedule, 0, $paymentNumber - 1);
        $skippedPayment = $originalSchedule[$paymentNumber - 1];
        $balance = $skippedPayment['balance'];
        $monthlyRate = ($skippedPayment['rate'] ?? 5.0) / 100 / 12;

        // Add skipped payment record
        $accruedInterest = $balance * $monthlyRate;
        $skippedPayment['skipped'] = true;
        $skippedPayment['accrued_interest'] = round($accruedInterest, 2);

        if ($capitalizeInterest) {
            $skippedPayment['balance'] = round($balance + $accruedInterest, 2);
        }

        $schedule[] = $skippedPayment;

        // Recalculate remaining payments
        $currentBalance = $skippedPayment['balance'];
        for ($i = $paymentNumber; $i < count($originalSchedule); $i++) {
            if ($currentBalance <= 0) break;

            $interest = $currentBalance * $monthlyRate;
            $payment = min(
                $this->calculatePayment($currentBalance, $monthlyRate, count($originalSchedule) - $i),
                $currentBalance + $interest
            );

            $principal = $payment - $interest;
            $currentBalance -= $principal;

            $schedule[] = [
                'payment_number' => $i + 1,
                'payment' => round($payment, 2),
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'balance' => round(max(0, $currentBalance), 2),
                'rate' => $skippedPayment['rate'] ?? 5.0,
                'skip_applied' => true,
            ];
        }

        return $schedule;
    }

    /**
     * Modify loan terms (rate, term, payment)
     * Used for refinance, loan modification, forbearance
     */
    public function modifyLoanTerms(
        array $originalSchedule,
        int $modificationMonth,
        ?float $newRate = null,
        ?int $newTerm = null,
        ?float $newPayment = null
    ): array {
        if ($modificationMonth < 1 || $modificationMonth > count($originalSchedule)) {
            throw new InvalidArgumentException('Invalid modification month');
        }

        $schedule = array_slice($originalSchedule, 0, $modificationMonth - 1);
        $modPayment = $originalSchedule[$modificationMonth - 1];
        $remainingBalance = $modPayment['balance'];

        if ($remainingBalance <= 0) {
            throw new InvalidArgumentException('Loan already paid off');
        }

        $rate = $newRate ?? ($modPayment['rate'] ?? 5.0);
        $term = $newTerm ?? (count($originalSchedule) - $modificationMonth + 1);

        // If new payment specified, calculate term
        if ($newPayment !== null) {
            $monthlyRate = $rate / 100 / 12;
            $term = $this->calculateTerm($remainingBalance, $monthlyRate, $newPayment);
            if ($term > 600) {
                throw new InvalidArgumentException('Payment too low for given term');
            }
            $payment = $newPayment;
        } else {
            $monthlyRate = $rate / 100 / 12;
            $payment = $this->calculatePayment($remainingBalance, $monthlyRate, $term);
        }

        // Recalculate schedule from modification point
        $currentBalance = $remainingBalance;
        $monthlyRate = $rate / 100 / 12;

        for ($i = 0; $i < $term && $currentBalance > 0; $i++) {
            $interest = $currentBalance * $monthlyRate;
            $principal = min($payment - $interest, $currentBalance);
            $currentBalance -= $principal;

            $schedule[] = [
                'payment_number' => $modificationMonth + $i,
                'payment' => round($payment, 2),
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'balance' => round(max(0, $currentBalance), 2),
                'rate' => $rate,
                'modified' => true,
                'modification_term' => $i + 1,
            ];
        }

        return $schedule;
    }

    /**
     * Generate alternative amortization schedules
     * Compares different scenarios side-by-side
     */
    public function generateAlternativeScenarios(
        float $principal,
        float $standardRate,
        int $standardMonths,
        array $scenarios = []
    ): array {
        $cacheKey = 'alternatives_' . md5(json_encode([
            'principal' => $principal,
            'rate' => $standardRate,
            'months' => $standardMonths,
            'scenarios' => $scenarios,
        ]));

        if ($cached = $this->cacheManager->get($cacheKey)) {
            return $cached;
        }

        $alternatives = [];

        if (!isset($scenarios['skip_standard'])) {
            $alternatives['standard'] = $this->generateBasicSchedule(
                $principal,
                $standardRate,
                $standardMonths
            );
        }

        // Scenario 1: Balloon payment (20% of principal at end)
        if (!isset($scenarios['skip_balloon'])) {
            $balloonAmount = $principal * 0.20;
            $alternatives['balloon_20pct'] = $this->generateBalloonPaymentSchedule(
                $principal,
                $standardRate,
                $standardMonths,
                $balloonAmount
            );
        }

        // Scenario 2: Accelerated (bi-weekly payments)
        if (!isset($scenarios['skip_accelerated'])) {
            $biweeklyMonths = max(12, intval($standardMonths / 2));
            $alternatives['biweekly'] = $this->generateBasicSchedule(
                $principal,
                $standardRate,
                $biweeklyMonths
            );
        }

        // Scenario 3: Higher rate shorter term
        if (!isset($scenarios['skip_higher_rate'])) {
            $higherRate = $standardRate + 1.0;
            $shorterMonths = max(12, intval($standardMonths * 0.75));
            $alternatives['higher_rate_short'] = $this->generateBasicSchedule(
                $principal,
                $higherRate,
                $shorterMonths
            );
        }

        // Scenario 4: Variable rate
        if (!isset($scenarios['skip_variable'])) {
            $variableRates = [
                $standardRate,
                $standardRate + 0.5,
                $standardRate + 1.0,
                $standardRate + 1.5,
            ];
            $monthsPerTerm = max(12, intval($standardMonths / 4));
            $alternatives['variable_stepped'] = $this->generateVariableRateSchedule(
                $principal,
                $variableRates,
                $monthsPerTerm
            );
        }

        // Calculate summary metrics for each scenario
        $summary = [];
        foreach ($alternatives as $name => $schedule) {
            $totalInterest = array_sum(array_column($schedule, 'interest'));
            $totalPayment = $principal + $totalInterest;
            $averagePayment = $totalPayment / count($schedule);

            $summary[$name] = [
                'num_payments' => count($schedule),
                'total_principal' => round($principal, 2),
                'total_interest' => round($totalInterest, 2),
                'total_payment' => round($totalPayment, 2),
                'average_payment' => round($averagePayment, 2),
                'first_payment' => round($schedule[0]['payment'] ?? 0, 2),
                'last_payment' => round($schedule[count($schedule) - 1]['payment'] ?? 0, 2),
            ];
        }

        $result = [
            'scenarios' => $alternatives,
            'summary' => $summary,
            'principal' => $principal,
            'standard_rate' => $standardRate,
            'standard_months' => $standardMonths,
        ];

        $this->cacheManager->set($cacheKey, $result, 3600);
        return $result;
    }

    /**
     * Helper: Generate basic amortization schedule
     */
    private function generateBasicSchedule(float $principal, float $rate, int $months): array
    {
        $monthlyRate = $rate / 100 / 12;
        $payment = $this->calculatePayment($principal, $monthlyRate, $months);
        
        $schedule = [];
        $balance = $principal;

        for ($i = 1; $i <= $months; $i++) {
            $interest = $balance * $monthlyRate;
            $principalPayment = $payment - $interest;
            $balance -= $principalPayment;

            $schedule[] = [
                'payment_number' => $i,
                'payment' => round($payment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interest, 2),
                'balance' => round(max(0, $balance), 2),
                'rate' => $rate,
            ];
        }

        return $schedule;
    }

    /**
     * Calculate monthly payment using standard amortization formula
     */
    private function calculatePayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate == 0 || $months <= 0) {
            return $principal / max(1, $months);
        }

        $numerator = $monthlyRate * ((1 + $monthlyRate) ** $months);
        $denominator = (1 + $monthlyRate) ** $months - 1;
        
        if ($denominator == 0) {
            return $principal / max(1, $months);
        }

        return $principal * ($numerator / $denominator);
    }

    /**
     * Calculate number of months needed to pay off loan at given payment
     */
    private function calculateTerm(float $principal, float $monthlyRate, float $payment): int
    {
        if ($monthlyRate == 0) {
            return (int)ceil($principal / $payment);
        }

        $term = -log(1 - ($principal * $monthlyRate) / $payment) / log(1 + $monthlyRate);
        return (int)ceil($term);
    }

    /**
     * Generate alternatives with variable rate schedule helper
     */
    private function generateAlternativeSchedules(float $principal, float $rate, int $months): array
    {
        $alternatives = [
            'standard' => $this->generateBasicSchedule($principal, $rate, $months),
        ];

        // Balloon (20%)
        $balloonAmount = $principal * 0.20;
        $alternatives['balloon_20pct'] = $this->generateBalloonPaymentSchedule(
            $principal,
            $rate,
            $months,
            $balloonAmount
        );

        // Biweekly (approx half payments)
        $biweeklyMonths = max(12, intval($months / 2));
        $alternatives['biweekly'] = $this->generateBasicSchedule(
            $principal,
            $rate,
            $biweeklyMonths
        );

        // Higher rate, shorter term
        $higherRate = $rate + 1.0;
        $shorterMonths = max(12, intval($months * 0.75));
        $alternatives['higher_rate_short'] = $this->generateBasicSchedule(
            $principal,
            $higherRate,
            $shorterMonths
        );

        // Variable rate simulation
        $variableRates = [
            $rate,
            $rate + 0.5,
            $rate + 1.0,
            $rate + 1.5,
        ];
        $monthsPerTerm = max(12, intval($months / 4));
        $alternatives['variable_stepped'] = $this->generateVariableRateSchedule(
            $principal,
            $variableRates,
            $monthsPerTerm
        );

        return $alternatives;
    }

    /**
     * Get comparison of total costs across scenarios
     */
    public function compareScenarioCosts(array $scenarios): array
    {
        $comparison = [];

        foreach ($scenarios as $name => $schedule) {
            if (!is_array($schedule) || empty($schedule)) {
                continue;
            }

            $totalInterest = array_sum(array_column($schedule, 'interest'));
            $totalPayment = array_sum(array_column($schedule, 'payment'));
            $principalPaid = array_sum(array_column($schedule, 'principal'));

            $comparison[$name] = [
                'total_payments' => count($schedule),
                'total_principal' => round($principalPaid, 2),
                'total_interest' => round($totalInterest, 2),
                'total_cost' => round($totalPayment, 2),
                'interest_percent' => $principalPaid > 0 ? round(($totalInterest / $principalPaid) * 100, 2) : 0,
            ];
        }

        usort($comparison, function ($a, $b) {
            return $a['total_cost'] <=> $b['total_cost'];
        });

        return $comparison;
    }
}
