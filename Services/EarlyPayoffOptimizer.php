<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTime;
use DateTimeImmutable;

/**
 * EarlyPayoffOptimizer
 *
 * Optimizes early payoff strategies with Monte Carlo simulations for variable rates,
 * lump-sum planning, and tax impact analysis.
 */
class EarlyPayoffOptimizer
{
    /**
     * @var DecimalCalculator
     */
    private $calculator;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Calculate extra monthly payment needed for target payoff months
     */
    public function calculateExtraMonthlyPaymentForTargetPayoff(Loan $loan, int $targetMonths): float
    {
        $monthlyRate = $this->calculator->divide($loan->getAnnualRate(), 12);
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());

        // Calculate payment needed for target term
        $targetPayment = $this->calculateMonthlyPayment($loan->getCurrentBalance(), $monthlyRate, $targetMonths);

        $extraPayment = $this->calculator->subtract($targetPayment, $monthlyPayment);
        return $this->calculator->round(max(0, $extraPayment), 2);
    }

    /**
     * Calculate payoff date with extra monthly payments
     */
    public function calculatePayoffDateWithExtraPayments(Loan $loan, float $extraPayment): string
    {
        $monthlyRate = $this->calculator->divide($loan->getAnnualRate(), 12);
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());
        $totalPayment = $this->calculator->add($monthlyPayment, $extraPayment);

        $balance = $loan->getCurrentBalance();
        $months = 0;

        while ($balance > 0.01 && $months < 600) {
            $interest = $this->calculator->multiply($balance, $monthlyRate);
            $principal = $this->calculator->subtract($totalPayment, $interest);

            if ($principal >= $balance) {
                break;
            }

            $balance = $this->calculator->subtract($balance, $principal);
            $months++;
        }

        $payoffDate = $loan->getStartDate()->modify("+{$months} months");
        return $payoffDate->format('Y-m-d');
    }

    /**
     * Analyze lump-sum payment scenarios
     */
    public function analyzeLumpSumPaymentScenarios(Loan $loan, array $lumpSumAmounts): array
    {
        $scenarios = [];
        $baseMonthlyRate = $this->calculator->divide($loan->getAnnualRate(), 12);
        $baseMonthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $baseMonthlyRate, $loan->getMonths());

        foreach ($lumpSumAmounts as $lumpSum) {
            $balance = $this->calculator->subtract($loan->getCurrentBalance(), $lumpSum);
            $balance = max(0, $balance);

            $months = $this->calculateMonthsToPayoff($balance, $baseMonthlyRate, $baseMonthlyPayment);
            $payoffDate = $loan->getStartDate()->modify("+{$months} months")->format('Y-m-d');

            $interestWithoutLump = $this->calculateTotalInterest($loan->getCurrentBalance(), $baseMonthlyPayment, $loan->getMonths());
            $interestWithLump = $this->calculateTotalInterest($balance, $baseMonthlyPayment, $months);
            $interestSaved = $this->calculator->subtract($interestWithoutLump, $interestWithLump);

            $scenarios[] = [
                'lump_sum' => $this->calculator->round($lumpSum, 2),
                'months_to_payoff' => $months,
                'payoff_date' => $payoffDate,
                'interest_saved' => $this->calculator->round(max(0, $interestSaved), 2),
                'total_interest_paid' => $this->calculator->round($interestWithLump, 2),
            ];
        }

        return $scenarios;
    }

    /**
     * Run Monte Carlo simulation for variable rate scenarios
     */
    public function runMonteCarloSimulation(Loan $loan, float $baseRate, float $volatility, int $simulations, float $payoffStrategy): array
    {
        $payoffMonths = [];

        for ($i = 0; $i < $simulations; $i++) {
            // Generate random rate within volatility range
            $randomRate = $this->calculator->add(
                $baseRate,
                ($this->randomBetween(-$volatility, $volatility) / 100)
            );

            $monthlyRate = $this->calculator->divide($randomRate, 12);
            $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());
            $extraPayment = $this->calculator->multiply($monthlyPayment, $payoffStrategy);
            $totalPayment = $this->calculator->add($monthlyPayment, $extraPayment);

            $balance = $loan->getCurrentBalance();
            $months = 0;

            while ($balance > 0.01 && $months < 600) {
                $interest = $this->calculator->multiply($balance, $monthlyRate);
                $principal = $this->calculator->subtract($totalPayment, $interest);

                if ($principal >= $balance) {
                    break;
                }

                $balance = $this->calculator->subtract($balance, $principal);
                $months++;
            }

            $payoffMonths[] = $months;
        }

        $mean = array_sum($payoffMonths) / count($payoffMonths);
        $variance = array_reduce($payoffMonths, fn($carry, $val) => $carry + pow($val - $mean, 2), 0) / count($payoffMonths);
        $stdDev = sqrt($variance);

        return [
            'simulations' => $simulations,
            'mean_payoff_months' => $this->calculator->round($mean, 2),
            'std_dev_payoff_months' => $this->calculator->round($stdDev, 2),
            'min_payoff_months' => min($payoffMonths),
            'max_payoff_months' => max($payoffMonths),
            'scenarios' => array_map(fn($m) => ['payoff_months' => $m], array_slice($payoffMonths, 0, 10)),
        ];
    }

    /**
     * Generate payoff optimization recommendation
     */
    public function generatePayoffOptimizationRecommendation(Loan $loan, float $annualIncome): array
    {
        $monthlyIncome = $this->calculator->divide($annualIncome, 12);
        $monthlyRate = $this->calculator->divide($loan->getAnnualRate(), 12);
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());

        // Recommend 20% of income toward accelerated payoff
        $recommendedExtra = $this->calculator->multiply($monthlyIncome, 0.2);

        $scenarios = [
            ['extra' => $this->calculator->round($this->calculator->multiply($monthlyPayment, 0.1), 2), 'label' => '10% extra'],
            ['extra' => $this->calculator->round($recommendedExtra, 2), 'label' => 'Recommended (20% income)'],
            ['extra' => $this->calculator->round($this->calculator->multiply($monthlyPayment, 0.5), 2), 'label' => '50% extra'],
        ];

        return [
            'recommended_strategy' => $scenarios[1],
            'rationale' => 'Balance accelerated payoff with financial flexibility',
            'scenarios' => $scenarios,
            'current_monthly_payment' => $this->calculator->round($monthlyPayment, 2),
            'monthly_income' => $this->calculator->round($monthlyIncome, 2),
        ];
    }

    /**
     * Calculate interest saved with accelerated payoff
     */
    public function calculateInterestSavedWithAcceleratedPayoff(Loan $loan, float $extraPayment, float $rate): float
    {
        $monthlyRate = $this->calculator->divide($rate, 12);
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());
        $totalPayment = $this->calculator->add($monthlyPayment, $extraPayment);

        $baseInterest = $this->calculateTotalInterest($loan->getCurrentBalance(), $monthlyPayment, $loan->getMonths());

        $balance = $loan->getCurrentBalance();
        $acceleratedInterest = 0;
        $months = 0;

        while ($balance > 0.01 && $months < 600) {
            $interest = $this->calculator->multiply($balance, $monthlyRate);
            $principal = $this->calculator->subtract($totalPayment, $interest);

            if ($principal >= $balance) {
                break;
            }

            $acceleratedInterest = $this->calculator->add($acceleratedInterest, $interest);
            $balance = $this->calculator->subtract($balance, $principal);
            $months++;
        }

        return $this->calculator->round(max(0, $this->calculator->subtract($baseInterest, $acceleratedInterest)), 2);
    }

    /**
     * Plan lump-sum payment strategy
     */
    public function planLumpSumPaymentStrategy(Loan $loan, array $lumpSumSchedule, float $rate): array
    {
        $totalLumpSums = array_sum($lumpSumSchedule);
        $monthlyRate = $this->calculator->divide($rate, 12);
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());

        $interestSaved = 0;
        $balance = $loan->getCurrentBalance();

        foreach ($lumpSumSchedule as $year => $amount) {
            $yearsFromStart = (int)str_replace('year_', '', $year);
            $baseInterestForYear = $this->calculateTotalInterest($balance, $monthlyPayment, 12);

            $balance = $this->calculator->subtract($balance, $amount);
            $balance = max(0, $balance);

            $acceleratedInterestForYear = $this->calculateTotalInterest($balance, $monthlyPayment, 12);
            $interestSaved = $this->calculator->add($interestSaved, $this->calculator->subtract($baseInterestForYear, $acceleratedInterestForYear));
        }

        $payoffMonths = $this->calculateMonthsToPayoff($balance, $monthlyRate, $monthlyPayment);
        $payoffDate = $loan->getStartDate()->modify("+{$payoffMonths} months")->format('Y-m-d');

        return [
            'lump_sum_schedule' => $lumpSumSchedule,
            'total_lump_sums' => $this->calculator->round($totalLumpSums, 2),
            'total_interest_saved' => $this->calculator->round($interestSaved, 2),
            'final_payoff_date' => $payoffDate,
            'months_to_payoff' => $payoffMonths,
        ];
    }

    /**
     * Analyze tax implications of payoff strategy
     */
    public function analyzeTaxImplications(Loan $loan, float $extraPayment, float $rate, float $annualIncome): array
    {
        $monthlyRate = $this->calculator->divide($rate, 12);
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());

        // Year 1 interest deduction (proxy)
        $baseInterestYear1 = 0;
        for ($i = 0; $i < 12; $i++) {
            $baseInterestYear1 += $this->calculator->multiply($loan->getCurrentBalance() - ($i * $monthlyPayment), $monthlyRate);
        }

        // Interest lost due to accelerated payoff
        $interestSaved = $this->calculateInterestSavedWithAcceleratedPayoff($loan, $extraPayment, $rate);

        // Tax benefit (approximate 24% bracket)
        $taxSavingsFromInterestDeduction = $this->calculator->multiply($baseInterestYear1, 0.24);
        $taxCostOfLostDeduction = $this->calculator->multiply($interestSaved, 0.24);
        $netTaxBenefit = $this->calculator->subtract($taxSavingsFromInterestDeduction, $taxCostOfLostDeduction);

        return [
            'lost_interest_deduction' => $this->calculator->round($interestSaved, 2),
            'tax_impact' => $this->calculator->round(max(0, $this->calculator->subtract($netTaxBenefit, 0)), 2),
            'net_benefit' => $this->calculator->round(max(0, $this->calculator->subtract($interestSaved, $this->calculator->multiply($netTaxBenefit, 0.5))), 2),
        ];
    }

    /**
     * Generate payoff comparison chart data
     */
    public function generatePayoffComparisonChartData(Loan $loan, array $extraPaymentAmounts): array
    {
        $strategies = [];

        foreach ($extraPaymentAmounts as $extra) {
            $payoffDate = $this->calculatePayoffDateWithExtraPayments($loan, $extra);
            $months = $this->countMonthsBetween($loan->getStartDate()->format('Y-m-d'), $payoffDate);
            $interestSaved = $this->calculateInterestSavedWithAcceleratedPayoff($loan, $extra, $loan->getAnnualRate());

            $strategies[] = [
                'extra_payment' => $this->calculator->round($extra, 2),
                'payoff_months' => $months,
                'payoff_date' => $payoffDate,
                'interest_saved' => $this->calculator->round($interestSaved, 2),
            ];
        }

        return ['strategies' => $strategies];
    }

    /**
     * Calculate minimum extra payment for target payoff
     */
    public function calculateMinimumExtraPaymentForGoal(Loan $loan, int $targetMonths): float
    {
        $monthlyRate = $this->calculator->divide($loan->getAnnualRate(), 12);
        $targetPayment = $this->calculateMonthlyPayment($loan->getCurrentBalance(), $monthlyRate, $targetMonths);
        $currentPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());

        $minExtra = $this->calculator->subtract($targetPayment, $currentPayment);
        return $this->calculator->round(max(0, $minExtra), 2);
    }

    /**
     * Analyze early payoff with inflation
     */
    public function analyzeEarlyPayoffWithInflation(Loan $loan, float $extraPayment, float $inflationRate, float $rate): array
    {
        $payoffDate = $this->calculatePayoffDateWithExtraPayments($loan, $extraPayment);
        $months = $this->countMonthsBetween($loan->getStartDate()->format('Y-m-d'), $payoffDate);

        // Real cost adjusted for inflation
        $monthlyInflation = pow(1 + $inflationRate, 1/12);
        $realCost = 0;
        $currentExtra = $extraPayment;

        for ($i = 0; $i < $months; $i++) {
            $realCost += $currentExtra;
            $currentExtra = $this->calculator->multiply($currentExtra, $monthlyInflation);
        }

        $realCostAdjusted = $this->calculator->divide($realCost, pow(1 + $inflationRate, $months / 12));

        return [
            'nominal_payoff_date' => $payoffDate,
            'months_to_payoff' => $months,
            'real_payoff_cost' => $this->calculator->round($realCostAdjusted, 2),
            'inflation_impact' => $this->calculator->round($this->calculator->subtract($realCost, $realCostAdjusted), 2),
        ];
    }

    /**
     * Export to JSON
     */
    public function exportToJSON(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Helper methods
     */
    private function calculateMonthlyPayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($months <= 0) {
            return 0;
        }

        if ($monthlyRate == 0) {
            return $this->calculator->divide($principal, $months);
        }

        $numerator = $this->calculator->multiply($monthlyRate, pow(1 + $monthlyRate, $months));
        $denominator = $this->calculator->subtract(pow(1 + $monthlyRate, $months), 1);

        return $this->calculator->multiply($principal, $this->calculator->divide($numerator, $denominator));
    }

    private function calculateTotalInterest(float $principal, float $monthlyPayment, int $months): float
    {
        $totalPaid = $this->calculator->multiply($monthlyPayment, $months);
        return $this->calculator->subtract($totalPaid, $principal);
    }

    private function calculateMonthsToPayoff(float $balance, float $monthlyRate, float $monthlyPayment): int
    {
        $months = 0;

        while ($balance > 0.01 && $months < 600) {
            $interest = $this->calculator->multiply($balance, $monthlyRate);
            $principal = $this->calculator->subtract($monthlyPayment, $interest);

            if ($principal >= $balance) {
                break;
            }

            $balance = $this->calculator->subtract($balance, $principal);
            $months++;
        }

        return $months;
    }

    private function randomBetween(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    private function countMonthsBetween(string $start, string $end): int
    {
        $startDate = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);
        $interval = $startDate->diff($endDate);
        return $interval->m + ($interval->y * 12);
    }
}
