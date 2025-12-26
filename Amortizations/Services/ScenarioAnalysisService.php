<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTimeImmutable;

/**
 * ScenarioAnalysisService - What-If Scenario Modeling
 *
 * Provides ephemeral scenario analysis and what-if calculations without
 * persisting changes to the database. Allows users to explore payment
 * strategies, compare interest savings, and identify optimal payoff approaches.
 *
 * Key Features:
 * - Create temporary scenarios with modifications
 * - Generate modified amortization schedules
 * - Compare multiple scenarios side-by-side
 * - Calculate total interest and payoff dates
 * - Save favorite scenarios (in-memory cache)
 * - Support multiple payment strategies
 *
 * Scenarios are ephemeral - no database persistence required.
 * Favorites can be stored in session/cache for user convenience.
 */
class ScenarioAnalysisService
{
    private $decimalCalculator;
    private $scenarios = [];
    private $favorites = [];

    public function __construct()
    {
        $this->decimalCalculator = new DecimalCalculator();
    }

    /**
     * Create a new scenario with specified modifications
     *
     * @param Loan $loan
     * @param string $name Scenario name
     * @param array $modifications Payment modifications (extra_monthly_payment, lump_sum_payment, etc.)
     * @return array Scenario configuration
     */
    public function createScenario(Loan $loan, string $name, array $modifications): array
    {
        $scenario = [
            'id' => uniqid('scenario_'),
            'name' => $name,
            'base_loan_id' => $loan->getId(),
            'modifications' => $modifications,
            'created_at' => date('Y-m-d H:i:s'),
            'is_favorite' => false,
        ];

        $this->scenarios[$scenario['id']] = $scenario;
        return $scenario;
    }

    /**
     * Generate amortization schedule for a scenario
     *
     * Applies modifications to the loan and calculates resulting schedule
     * without persisting any changes.
     *
     * @param array $scenario
     * @param Loan $loan
     * @return array Schedule periods with dates, payments, interest, principal
     */
    public function generateScenarioSchedule(array $scenario, Loan $loan): array
    {
        $modifications = $scenario['modifications'];
        $periods = [];
        
        $balance = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($loan);
        
        // Start date
        $currentDate = $loan->getStartDate() ?? new DateTimeImmutable('2024-01-01');
        $month = 1;
        $maxMonths = $loan->getMonths();

        // Apply lump-sum payment if specified
        $lumpSumMonth = $modifications['lump_sum_month'] ?? null;
        $lumpSumAmount = $modifications['lump_sum_payment'] ?? 0;

        // Calculate with extra monthly payment
        $extraMonthly = $modifications['extra_monthly_payment'] ?? 0;

        while ($balance > 0.01 && $month <= $maxMonths * 2) {
            $interest = round($balance * $monthlyRate, 2);
            $payment = $monthlyPayment + $extraMonthly;

            // Apply lump sum if specified month
            if ($month === $lumpSumMonth) {
                $payment += $lumpSumAmount;
            }

            $principal = $payment - $interest;

            // Adjust for final payment
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

            // Stop if paid off
            if ($balance <= 0) {
                break;
            }
        }

        return [
            'periods' => $periods,
            'total_periods' => count($periods),
            'scenario_id' => $scenario['id'],
        ];
    }

    /**
     * Compare two scenarios side-by-side
     *
     * @param array $scenario1
     * @param array $schedule1 From generateScenarioSchedule
     * @param array $scenario2
     * @param array $schedule2 From generateScenarioSchedule
     * @return array Comparison metrics
     */
    public function compareScenarios(
        array $scenario1,
        array $schedule1,
        array $scenario2,
        array $schedule2
    ): array {
        $interest1 = $this->calculateTotalInterest($schedule1);
        $interest2 = $this->calculateTotalInterest($schedule2);
        $periods1 = count($schedule1['periods']);
        $periods2 = count($schedule2['periods']);

        return [
            'scenario1_name' => $scenario1['name'],
            'scenario2_name' => $scenario2['name'],
            'scenario1_periods' => $periods1,
            'scenario2_periods' => $periods2,
            'scenario1_total_interest' => round($interest1, 2),
            'scenario2_total_interest' => round($interest2, 2),
            'interest_saved' => round(abs($interest1 - $interest2), 2),
            'period_reduction' => abs($periods1 - $periods2),
            'payoff_acceleration' => $periods1 > $periods2 ? $scenario2['name'] : $scenario1['name'],
        ];
    }

    /**
     * Calculate total interest paid in scenario
     *
     * @param array $schedule
     * @return float Total interest
     */
    public function calculateTotalInterest(array $schedule): float
    {
        $totalInterest = 0.0;

        foreach ($schedule['periods'] as $period) {
            $totalInterest += $period['interest'];
        }

        return round($totalInterest, 2);
    }

    /**
     * Calculate total payments in scenario
     *
     * @param array $schedule
     * @return float Total payments made
     */
    public function calculateTotalPayments(array $schedule): float
    {
        $totalPayments = 0.0;

        foreach ($schedule['periods'] as $period) {
            $totalPayments += $period['payment'];
        }

        return round($totalPayments, 2);
    }

    /**
     * Calculate payoff date for scenario
     *
     * Returns the date of the final payment
     *
     * @param array $schedule
     * @return DateTimeImmutable Payoff date
     */
    public function calculatePayoffDate(array $schedule): DateTimeImmutable
    {
        if (empty($schedule['periods'])) {
            return new DateTimeImmutable();
        }

        $lastPeriod = end($schedule['periods']);
        return new DateTimeImmutable($lastPeriod['date']);
    }

    /**
     * Save scenario as favorite for quick access
     *
     * @param array $scenario
     * @param int $loanId
     * @return bool
     */
    public function saveAsFavorite(array &$scenario, int $loanId): bool
    {
        $scenario['is_favorite'] = true;

        if (!isset($this->favorites[$loanId])) {
            $this->favorites[$loanId] = [];
        }

        $this->favorites[$loanId][] = $scenario;
        return true;
    }

    /**
     * Get all favorite scenarios for a loan
     *
     * @param int $loanId
     * @return array Array of favorite scenarios
     */
    public function getFavoriteScenarios(int $loanId): array
    {
        return $this->favorites[$loanId] ?? [];
    }

    /**
     * Delete scenario
     *
     * @param string $scenarioId
     * @return bool
     */
    public function deleteScenario(string $scenarioId): bool
    {
        // Remove from scenarios
        unset($this->scenarios[$scenarioId]);

        // Remove from all favorites
        foreach ($this->favorites as &$loanFavorites) {
            $loanFavorites = array_filter(
                $loanFavorites,
                fn($s) => $s['id'] !== $scenarioId
            );
        }

        return true;
    }

    /**
     * Compare multiple strategies for a loan
     *
     * Generates a comparison matrix for multiple payment strategies
     *
     * @param Loan $loan
     * @param array $strategies Array of strategy configs
     * @return array Comparison matrix with metrics for each strategy
     */
    public function compareMultipleStrategies(Loan $loan, array $strategies): array
    {
        $results = [];

        foreach ($strategies as $strategyConfig) {
            $scenario = $this->createScenario(
                $loan,
                $strategyConfig['name'],
                $strategyConfig['modifications']
            );

            $schedule = $this->generateScenarioSchedule($scenario, $loan);

            $results[] = [
                'strategy_name' => $strategyConfig['name'],
                'total_periods' => count($schedule['periods']),
                'total_interest' => $this->calculateTotalInterest($schedule),
                'total_payments' => $this->calculateTotalPayments($schedule),
                'payoff_date' => $this->calculatePayoffDate($schedule)->format('Y-m-d'),
                'period_savings' => $loan->getMonths() - count($schedule['periods']),
                'interest_savings' => $this->getBaselineInterest($loan) - $this->calculateTotalInterest($schedule),
            ];
        }

        return $results;
    }

    /**
     * Get scenario by ID
     *
     * @param string $scenarioId
     * @return array|null
     */
    public function getScenario(string $scenarioId): ?array
    {
        return $this->scenarios[$scenarioId] ?? null;
    }

    /**
     * Calculate baseline interest (no extra payments)
     *
     * @param Loan $loan
     * @return float Baseline total interest
     */
    private function getBaselineInterest(Loan $loan): float
    {
        $scenario = $this->createScenario($loan, 'baseline', []);
        $schedule = $this->generateScenarioSchedule($scenario, $loan);
        return $this->calculateTotalInterest($schedule);
    }

    /**
     * Calculate monthly payment using standard amortization formula
     *
     * P = L * (r * (1+r)^n) / ((1+r)^n - 1)
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

        $payment = $principal * ($numerator / $denominator);

        return round($payment, 2);
    }
}
