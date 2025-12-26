<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTime;

/**
 * PortfolioAnalyticsService
 *
 * Analyzes multi-loan portfolios with aggregate metrics, risk scoring,
 * concentration analysis, and performance dashboards.
 */
class PortfolioAnalyticsService
{
    private DecimalCalculator $calculator;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Calculate total principal across all loans
     */
    public function calculateTotalPortfolioPrincipal(array $loans): float
    {
        $total = 0;
        foreach ($loans as $loan) {
            $total += $loan->getPrincipal();
        }
        return $this->calculator->round($total, 2);
    }

    /**
     * Calculate total current balance across all loans
     */
    public function calculateTotalPortfolioCurrentBalance(array $loans): float
    {
        $total = 0;
        foreach ($loans as $loan) {
            $total += $loan->getCurrentBalance();
        }
        return $this->calculator->round($total, 2);
    }

    /**
     * Calculate weighted average rate for portfolio
     */
    public function calculateWeightedAverageRate(array $loans): float
    {
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);
        if ($totalPrincipal == 0) {
            return 0;
        }

        $weightedRate = 0;
        foreach ($loans as $loan) {
            $weight = $this->calculator->divide($loan->getPrincipal(), $totalPrincipal);
            $weightedRate += $this->calculator->multiply($loan->getAnnualRate(), $weight);
        }

        return $this->calculator->round($weightedRate, 6);
    }

    /**
     * Analyze portfolio composition
     */
    public function analyzePortfolioComposition(array $loans): array
    {
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);
        $totalBalance = $this->calculateTotalPortfolioCurrentBalance($loans);
        $totalEquity = $this->calculator->subtract($totalPrincipal, $totalBalance);

        return [
            'total_loans' => count($loans),
            'total_principal' => $this->calculator->round($totalPrincipal, 2),
            'total_balance' => $this->calculator->round($totalBalance, 2),
            'total_equity' => $this->calculator->round($totalEquity, 2),
            'avg_loan_size' => $this->calculator->round($this->calculator->divide($totalPrincipal, count($loans)), 2),
        ];
    }

    /**
     * Calculate portfolio risk score (0-100)
     */
    public function calculatePortfolioRiskScore(array $loans): float
    {
        $rates = array_map(fn($loan) => $loan->getAnnualRate(), $loans);
        $avgRate = array_sum($rates) / count($rates);

        // Risk: variance in rates + concentration
        $variance = 0;
        foreach ($rates as $rate) {
            $variance += pow($rate - $avgRate, 2);
        }
        $variance /= count($rates);
        $stdev = sqrt($variance);

        $concentration = $this->calculateHerfindahlIndex($loans);

        // Combine rate volatility (0-30 points) + concentration (0-70 points)
        $rateRisk = min(30, $stdev * 100);
        $concRisk = min(70, $concentration * 100);

        return $this->calculator->round($rateRisk + $concRisk, 2);
    }

    /**
     * Identify high-risk loans within portfolio
     */
    public function identifyHighRiskLoans(array $loans): array
    {
        $avgRate = $this->calculateWeightedAverageRate($loans);
        $avgTerm = array_sum(array_map(fn($l) => $l->getMonths(), $loans)) / count($loans);

        $highRisk = [];
        foreach ($loans as $loan) {
            $rateDeviation = $loan->getAnnualRate() - $avgRate;
            $termDeviation = $loan->getMonths() - $avgTerm;

            // High risk if >0.5% above avg rate or >50% longer term
            if ($rateDeviation > 0.005 || $termDeviation > ($avgTerm * 0.5)) {
                $highRisk[] = [
                    'loan_id' => $loan->getId(),
                    'principal' => $loan->getPrincipal(),
                    'rate' => $loan->getAnnualRate(),
                    'months' => $loan->getMonths(),
                    'risk_factors' => [
                        'rate_deviation' => $this->calculator->round($rateDeviation, 4),
                        'term_deviation' => $this->calculator->round($termDeviation, 2),
                    ],
                ];
            }
        }

        return $highRisk;
    }

    /**
     * Calculate Herfindahl index (concentration metric)
     */
    private function calculateHerfindahlIndex(array $loans): float
    {
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);
        if ($totalPrincipal == 0) {
            return 0;
        }

        $herfindahl = 0;
        foreach ($loans as $loan) {
            $share = $this->calculator->divide($loan->getPrincipal(), $totalPrincipal);
            $herfindahl += pow($share, 2);
        }

        return $herfindahl;
    }

    /**
     * Calculate portfolio concentration metrics
     */
    public function calculatePortfolioConcentrationMetrics(array $loans): array
    {
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);
        $herfindahl = $this->calculateHerfindahlIndex($loans);

        $principals = array_map(fn($l) => $l->getPrincipal(), $loans);
        $maxPrincipal = max($principals);
        $maxPercentage = $this->calculator->divide($maxPrincipal, $totalPrincipal);

        // Concentration levels: 0-0.33 low, 0.33-0.5 medium, >0.5 high
        $concentrationLevel = $herfindahl < 0.33 ? 'low' : ($herfindahl < 0.5 ? 'medium' : 'high');

        return [
            'herfindahl_index' => $this->calculator->round($herfindahl, 4),
            'max_loan_percentage' => $this->calculator->round($maxPercentage, 4),
            'concentration_level' => $concentrationLevel,
            'loan_count' => count($loans),
        ];
    }

    /**
     * Generate portfolio performance dashboard
     */
    public function generatePortfolioPerformanceDashboard(array $loans): array
    {
        $composition = $this->analyzePortfolioComposition($loans);
        $riskScore = $this->calculatePortfolioRiskScore($loans);
        $concentration = $this->calculatePortfolioConcentrationMetrics($loans);
        $qualityScore = $this->calculatePortfolioQualityScore($loans);

        $loanDetails = [];
        foreach ($loans as $loan) {
            $loanDetails[] = [
                'loan_id' => $loan->getId(),
                'principal' => $loan->getPrincipal(),
                'balance' => $loan->getCurrentBalance(),
                'rate' => $loan->getAnnualRate(),
                'months' => $loan->getMonths(),
            ];
        }

        return [
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
            'total_principal' => $composition['total_principal'],
            'total_balance' => $composition['total_balance'],
            'total_equity' => $composition['total_equity'],
            'weighted_avg_rate' => $this->calculateWeightedAverageRate($loans),
            'loan_count' => $composition['total_loans'],
            'risk_score' => $riskScore,
            'quality_score' => $qualityScore,
            'concentration' => $concentration,
            'loans' => $loanDetails,
        ];
    }

    /**
     * Calculate total equity across portfolio
     */
    public function calculatePortfolioEquityPosition(array $loans): float
    {
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);
        $totalBalance = $this->calculateTotalPortfolioCurrentBalance($loans);
        return $this->calculator->round($this->calculator->subtract($totalPrincipal, $totalBalance), 2);
    }

    /**
     * Compare performance of each loan within portfolio
     */
    public function compareLoanPerformanceWithinPortfolio(array $loans): array
    {
        $comparison = [];
        foreach ($loans as $loan) {
            $payoffPercentage = $this->calculator->divide(
                $this->calculator->subtract($loan->getPrincipal(), $loan->getCurrentBalance()),
                $loan->getPrincipal()
            );

            $comparison[] = [
                'loan_id' => $loan->getId(),
                'principal' => $loan->getPrincipal(),
                'current_balance' => $loan->getCurrentBalance(),
                'amount_paid' => $this->calculator->round(
                    $this->calculator->subtract($loan->getPrincipal(), $loan->getCurrentBalance()),
                    2
                ),
                'payoff_percentage' => $this->calculator->round($payoffPercentage, 4),
                'rate' => $loan->getAnnualRate(),
                'months' => $loan->getMonths(),
            ];
        }

        // Sort by payoff percentage (ascending - lowest first)
        usort($comparison, fn($a, $b) => $a['payoff_percentage'] <=> $b['payoff_percentage']);
        return $comparison;
    }

    /**
     * Calculate portfolio debt-to-income ratio
     */
    public function calculatePortfolioDebtRatio(array $loans, float $annualIncome): float
    {
        if ($annualIncome == 0) {
            return 0;
        }

        $totalBalance = $this->calculateTotalPortfolioCurrentBalance($loans);
        return $this->calculator->round($this->calculator->divide($totalBalance, $annualIncome), 4);
    }

    /**
     * Export analytics to JSON
     */
    public function exportToJSON(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Identify rebalancing opportunities
     */
    public function identifyRebalancingOpportunities(array $loans): array
    {
        $opportunities = [];
        $avgRate = $this->calculateWeightedAverageRate($loans);
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);

        foreach ($loans as $loan) {
            $rateDeviation = $loan->getAnnualRate() - $avgRate;
            $loanPercentage = $this->calculator->divide($loan->getPrincipal(), $totalPrincipal);

            // High concentration + above-average rate = refinance candidate
            if ($loanPercentage > 0.4 && $rateDeviation > 0.005) {
                $opportunities[] = [
                    'loan_id' => $loan->getId(),
                    'reason' => 'High concentration + above-average rate',
                    'current_rate' => $loan->getAnnualRate(),
                    'portfolio_avg_rate' => $avgRate,
                    'portfolio_percentage' => $this->calculator->round($loanPercentage, 4),
                    'potential_savings' => $this->calculatePotentialSavings(
                        $loan->getPrincipal(),
                        $loan->getAnnualRate(),
                        $avgRate
                    ),
                ];
            }
        }

        return $opportunities;
    }

    /**
     * Calculate potential savings from refinancing
     */
    private function calculatePotentialSavings(float $principal, float $currentRate, float $targetRate): float
    {
        $savings = $this->calculator->multiply(
            $principal,
            $this->calculator->subtract($currentRate, $targetRate)
        );
        return $this->calculator->round($savings, 2);
    }

    /**
     * Calculate aggregate interest paid at given rate
     */
    public function calculateAggregateInterestPaid(array $loans, float $avgRate): float
    {
        $totalInterest = 0;
        foreach ($loans as $loan) {
            // Simple: balance * rate (proxy for remaining interest)
            $totalInterest += $this->calculator->multiply($loan->getCurrentBalance(), $avgRate);
        }
        return $this->calculator->round($totalInterest, 2);
    }

    /**
     * Generate loan-by-loan analytics report
     */
    public function generateLoanByLoanAnalyticsReport(array $loans): array
    {
        $report = [];
        $totalPrincipal = $this->calculateTotalPortfolioPrincipal($loans);

        foreach ($loans as $loan) {
            $payoffPercentage = $this->calculator->divide(
                $this->calculator->subtract($loan->getPrincipal(), $loan->getCurrentBalance()),
                $loan->getPrincipal()
            );
            $portfolioShare = $this->calculator->divide($loan->getPrincipal(), $totalPrincipal);

            $report[] = [
                'loan_id' => $loan->getId(),
                'principal' => $loan->getPrincipal(),
                'current_balance' => $loan->getCurrentBalance(),
                'rate' => $loan->getAnnualRate(),
                'months' => $loan->getMonths(),
                'payoff_percentage' => $this->calculator->round($payoffPercentage, 4),
                'portfolio_share' => $this->calculator->round($portfolioShare, 4),
                'months_remaining' => $this->calculateMonthsRemaining($loan),
            ];
        }

        return $report;
    }

    /**
     * Calculate estimated months remaining for a loan
     */
    private function calculateMonthsRemaining(Loan $loan): int
    {
        $payoffPercentage = $this->calculator->divide(
            $this->calculator->subtract($loan->getPrincipal(), $loan->getCurrentBalance()),
            $loan->getPrincipal()
        );
        return (int)round($loan->getMonths() * (1 - $payoffPercentage));
    }

    /**
     * Calculate portfolio quality score (0-100)
     */
    public function calculatePortfolioQualityScore(array $loans): float
    {
        // Quality factors: rate diversity, balanced distribution, reasonable terms
        $riskScore = $this->calculatePortfolioRiskScore($loans);
        $quality = 100 - $riskScore;  // Inverse of risk

        return $this->calculator->round(max(0, $quality), 2);
    }
}
