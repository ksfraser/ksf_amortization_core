<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use DateTimeImmutable;

/**
 * PaymentStrategyAnalyzer - Comparative Strategy Analysis
 *
 * Provides comprehensive comparison of multiple payment strategies and
 * recommends optimal approaches based on user goals (minimize interest,
 * fastest payoff, balance affordability/speed, etc.).
 *
 * Key Features:
 * - Compare multiple payment strategies side-by-side
 * - Calculate ROI and cost-benefit for each strategy
 * - Recommend optimal strategy based on goals
 * - Generate comparison matrices and visualizations
 * - Support multiple strategy types (accelerated, frequency changes, etc.)
 * - Break-even and ROI calculations
 */
class PaymentStrategyAnalyzer
{
    /**
     * Analyze multiple strategies for a loan
     *
     * @param Loan $loan
     * @param array $strategies Array of strategy configurations
     * @return array Analysis results for each strategy
     */
    public function analyzeStrategies(Loan $loan, array $strategies): array
    {
        $results = [];

        foreach ($strategies as $strategy) {
            $schedule = $this->generateStrategySchedule($loan, $strategy);
            $totalInterest = $this->calculateTotalInterest($schedule);
            $periods = count($schedule['periods']);

            $results[] = [
                'name' => $strategy['name'],
                'type' => $strategy['type'],
                'total_interest' => round($totalInterest, 2),
                'payoff_months' => $periods,
                'total_payments' => $this->calculateTotalPayments($schedule),
            ];
        }

        return $results;
    }

    /**
     * Generate amortization schedule for a strategy
     *
     * @param Loan $loan
     * @param array $strategy Strategy configuration
     * @return array Schedule periods
     */
    public function generateStrategySchedule(Loan $loan, array $strategy): array
    {
        $periods = [];
        $balance = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($loan);
        
        $currentDate = $loan->getStartDate() ?? new DateTimeImmutable('2024-01-01');
        $month = 1;
        $maxMonths = $loan->getMonths() * 2;

        $extraPayment = $strategy['extra_payment'] ?? 0;

        while ($balance > 0.01 && $month <= $maxMonths) {
            $interest = round($balance * $monthlyRate, 2);
            $payment = $monthlyPayment + $extraPayment;
            $principal = $payment - $interest;

            if ($principal >= $balance) {
                $principal = $balance;
                $payment = $principal + $interest;
            }

            $balance -= $principal;
            $balance = max(0, $balance);

            $periods[] = [
                'period' => $month,
                'date' => $currentDate->format('Y-m-d'),
                'payment' => round($payment, 2),
                'interest' => $interest,
                'principal' => round($principal, 2),
                'balance' => round($balance, 2),
            ];

            $currentDate = $currentDate->modify('+1 month');
            $month++;

            if ($balance <= 0) {
                break;
            }
        }

        return ['periods' => $periods];
    }

    /**
     * Calculate total interest for schedule
     *
     * @param array $schedule
     * @return float Total interest
     */
    public function calculateTotalInterest(array $schedule): float
    {
        $total = 0.0;
        foreach ($schedule['periods'] as $period) {
            $total += $period['interest'];
        }
        return round($total, 2);
    }

    /**
     * Calculate total payments for schedule
     *
     * @param array $schedule
     * @return float Total payments
     */
    public function calculateTotalPayments(array $schedule): float
    {
        $total = 0.0;
        foreach ($schedule['periods'] as $period) {
            $total += $period['payment'];
        }
        return round($total, 2);
    }

    /**
     * Calculate extra cost of a strategy
     *
     * For accelerated strategies, calculate total extra payments made
     *
     * @param array $strategy
     * @param int $periods Number of periods
     * @return float Total extra cost
     */
    public function calculateStrategyExtraCost(array $strategy, int $periods): float
    {
        $extraPayment = $strategy['extra_payment'] ?? 0;
        return round($extraPayment * $periods, 2);
    }

    /**
     * Calculate ROI for strategy
     *
     * ROI = (Interest Saved - Extra Cost) / Extra Cost
     *
     * @param float $interestSavings
     * @param float $extraCost
     * @return float ROI percentage
     */
    public function calculateROI(float $interestSavings, float $extraCost): float
    {
        if ($extraCost == 0) {
            return 0.0;
        }
        return round(($interestSavings - $extraCost) / $extraCost * 100, 2);
    }

    /**
     * Calculate break-even month for strategy
     *
     * Month when cumulative interest savings exceed cumulative extra payments
     *
     * @param Loan $loan
     * @param array $strategy
     * @return int Break-even month
     */
    public function calculateBreakEvenMonth(Loan $loan, array $strategy): int
    {
        $baselineSchedule = $this->generateStrategySchedule($loan, ['extra_payment' => 0]);
        $strategySchedule = $this->generateStrategySchedule($loan, $strategy);

        $extraPayment = $strategy['extra_payment'] ?? 0;
        $cumulativeExtra = 0;
        $cumulativeSavings = 0;

        for ($i = 0; $i < count($strategySchedule['periods']); $i++) {
            if (isset($baselineSchedule['periods'][$i])) {
                $baselineInterest = $baselineSchedule['periods'][$i]['interest'];
            } else {
                $baselineInterest = 0;
            }

            $strategyInterest = $strategySchedule['periods'][$i]['interest'];
            $savings = $baselineInterest - $strategyInterest;

            $cumulativeExtra += $extraPayment;
            $cumulativeSavings += $savings;

            if ($cumulativeSavings >= $cumulativeExtra) {
                return $i + 1;
            }
        }

        return count($strategySchedule['periods']);
    }

    /**
     * Recommend optimal strategy based on goal
     *
     * @param Loan $loan
     * @param array $strategies
     * @param string $goal (minimize_interest|fastest_payoff|balance|best_roi)
     * @return array Recommended strategy with rationale
     */
    public function recommendStrategy(Loan $loan, array $strategies, string $goal): array
    {
        $analyses = $this->analyzeStrategies($loan, $strategies);

        $recommended = null;

        switch ($goal) {
            case 'minimize_interest':
                $recommended = min($analyses, fn($a, $b) => 
                    $a['total_interest'] <=> $b['total_interest']
                );
                $rationale = 'Minimizes total interest paid';
                break;

            case 'fastest_payoff':
                $recommended = min($analyses, fn($a, $b) => 
                    $a['payoff_months'] <=> $b['payoff_months']
                );
                $rationale = 'Shortest payoff period';
                break;

            default:
                $recommended = $analyses[0];
                $rationale = 'Default strategy';
        }

        return [
            'strategy' => $recommended,
            'goal' => $goal,
            'rationale' => $rationale,
        ];
    }

    /**
     * Find optimal extra payment amount
     *
     * Analyzes a range of extra payment amounts and identifies optimal
     *
     * @param Loan $loan
     * @param array $amounts Extra payment amounts to test
     * @return array Optimal configuration
     */
    public function findOptimalExtraPaymentAmount(Loan $loan, array $amounts): array
    {
        $best = null;
        $bestMetrics = null;

        foreach ($amounts as $amount) {
            $strategy = ['extra_payment' => $amount];
            $schedule = $this->generateStrategySchedule($loan, $strategy);
            $totalInterest = $this->calculateTotalInterest($schedule);
            $periods = count($schedule['periods']);

            $metrics = [
                'amount' => $amount,
                'total_interest' => $totalInterest,
                'payoff_months' => $periods,
                'roi' => $this->calculateROI(
                    $this->calculateTotalInterest($this->generateStrategySchedule($loan, ['extra_payment' => 0])) - $totalInterest,
                    $amount * $periods
                ),
            ];

            if ($best === null || $metrics['roi'] > $bestMetrics['roi']) {
                $best = $amount;
                $bestMetrics = $metrics;
            }
        }

        return $bestMetrics;
    }

    /**
     * Generate comparison matrix for multiple strategies
     *
     * @param Loan $loan
     * @param array $strategies
     * @return array Comparison matrix with visualization data
     */
    public function generateComparisonMatrix(Loan $loan, array $strategies): array
    {
        $analyses = $this->analyzeStrategies($loan, $strategies);

        // Baseline (first strategy)
        $baseline = $analyses[0];

        $metrics = [];
        foreach ($analyses as $analysis) {
            $metrics[] = [
                'name' => $analysis['name'],
                'interest_saved_vs_baseline' => round($baseline['total_interest'] - $analysis['total_interest'], 2),
                'months_saved_vs_baseline' => $baseline['payoff_months'] - $analysis['payoff_months'],
                'total_interest' => $analysis['total_interest'],
                'payoff_months' => $analysis['payoff_months'],
            ];
        }

        return [
            'strategies' => $strategies,
            'metrics' => $metrics,
        ];
    }

    /**
     * Calculate cost-benefit analysis for strategy
     *
     * @param Loan $loan
     * @param array $strategy
     * @return array Cost-benefit breakdown
     */
    public function calculateCostBenefit(Loan $loan, array $strategy): array
    {
        $baselineSchedule = $this->generateStrategySchedule($loan, ['extra_payment' => 0]);
        $strategySchedule = $this->generateStrategySchedule($loan, $strategy);

        $baselineInterest = $this->calculateTotalInterest($baselineSchedule);
        $strategyInterest = $this->calculateTotalInterest($strategySchedule);
        
        $extraPayment = $strategy['extra_payment'] ?? 0;
        $periods = count($strategySchedule['periods']);
        $totalExtraCost = $extraPayment * $periods;
        $interestSavings = $baselineInterest - $strategyInterest;

        return [
            'total_extra_cost' => round($totalExtraCost, 2),
            'total_interest_savings' => round($interestSavings, 2),
            'net_benefit' => round($interestSavings - $totalExtraCost, 2),
            'benefit_ratio' => round($interestSavings / max($totalExtraCost, 0.01), 2),
        ];
    }

    /**
     * Generate comprehensive recommendation summary
     *
     * @param Loan $loan
     * @param array $strategies
     * @return array Summary with recommendation and visualization data
     */
    public function generateRecommendationSummary(Loan $loan, array $strategies): array
    {
        $analyses = $this->analyzeStrategies($loan, $strategies);
        $recommended = $this->recommendStrategy($loan, $strategies, 'minimize_interest');

        $baseline = $analyses[0];
        $best = min($analyses, fn($a, $b) => 
            $a['total_interest'] <=> $b['total_interest']
        );

        return [
            'recommended_strategy' => $recommended['strategy']['name'],
            'interest_saved' => round($baseline['total_interest'] - $best['total_interest'], 2),
            'months_to_payoff' => $best['payoff_months'],
            'comparison_data' => $analyses,
            'timeline' => [
                'baseline_months' => $baseline['payoff_months'],
                'accelerated_months' => $best['payoff_months'],
                'months_reduction' => $baseline['payoff_months'] - $best['payoff_months'],
            ],
        ];
    }

    /**
     * Calculate monthly payment
     *
     * @param Loan $loan
     * @return float Monthly payment
     */
    private function calculateMonthlyPayment(Loan $loan): float
    {
        $principal = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $numPayments = $loan->getMonths();

        if ($monthlyRate <= 0) {
            return round($principal / $numPayments, 2);
        }

        $denominator = pow(1 + $monthlyRate, $numPayments) - 1;
        $numerator = $monthlyRate * pow(1 + $monthlyRate, $numPayments);

        return round($principal * ($numerator / $denominator), 2);
    }
}
