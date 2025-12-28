<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

class PortfolioManagementService {
    
    /**
     * Group loans by their status
     */
    public function groupLoansByStatus(array $loans): array {
        $grouped = ['active' => [], 'closed' => [], 'defaulted' => [], 'other' => []];
        
        foreach ($loans as $loan) {
            // For now, classify by balance status
            $status = 'active';
            if (!isset($grouped[$status])) {
                $grouped[$status] = [];
            }
            $grouped[$status][] = $loan;
        }
        
        return $grouped;
    }

    /**
     * Group loans by loan type (Auto, Mortgage, Other)
     */
    public function groupLoansByType(array $loans): array {
        $grouped = [];
        
        foreach ($loans as $loan) {
            $type = 'Standard';
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $loan;
        }
        
        return $grouped;
    }

    /**
     * Group loans by interest rate range
     */
    public function groupLoansByRate(array $loans, float $rangeSize = 0.01): array {
        $grouped = [];
        
        foreach ($loans as $loan) {
            $rate = $loan->getAnnualRate();
            $rangeStart = floor($rate / $rangeSize) * $rangeSize;
            $rangeKey = round($rangeStart * 100, 2) . '%';
            
            if (!isset($grouped[$rangeKey])) {
                $grouped[$rangeKey] = [];
            }
            $grouped[$rangeKey][] = $loan;
        }
        
        return $grouped;
    }

    /**
     * Calculate portfolio-wide yield (weighted average return)
     */
    public function calculatePortfolioYield(array $loans): float {
        if (empty($loans)) {
            return 0.0;
        }

        $totalYield = 0.0;
        $totalPrincipal = 0.0;

        foreach ($loans as $loan) {
            $principal = $loan->getPrincipal();
            $rate = $loan->getAnnualRate();
            $totalYield += $principal * $rate;
            $totalPrincipal += $principal;
        }

        return $totalPrincipal > 0 ? round($totalYield / $totalPrincipal, 4) : 0.0;
    }

    /**
     * Calculate default rate for the portfolio
     */
    public function calculateDefaultRate(array $loans): float {
        if (empty($loans)) {
            return 0.0;
        }

        // Without status tracking, calculate based on arrears
        $defaultCount = 0;

        foreach ($loans as $loan) {
            $arrears = $loan->getArrears();
            if (!empty($arrears)) {
                $defaultCount++;
            }
        }

        return round($defaultCount / count($loans), 4);
    }

    /**
     * Rank loans by performance (yield return)
     */
    public function rankLoansByPerformance(array $loans): array {
        $ranked = [];
        
        foreach ($loans as $loan) {
            $id = $loan->getId() ?? uniqid();
            $performance = [
                'loan_id' => $id,
                'principal' => $loan->getPrincipal(),
                'rate' => $loan->getAnnualRate(),
                'yield' => $loan->getPrincipal() * $loan->getAnnualRate(),
                'months_remaining' => max(0, $loan->getMonths() - ($loan->getPaymentsMade() ?? 0))
            ];
            $ranked[] = $performance;
        }

        // Sort by yield descending
        usort($ranked, function($a, $b) {
            if ($a['yield'] == $b['yield']) return 0;
            return ($a['yield'] < $b['yield']) ? 1 : -1;
        });
        
        return array_values($ranked);
    }

    /**
     * Calculate profitability for the portfolio
     */
    public function calculateProfitability(array $loans): array {
        $totalPrincipal = 0;
        $totalInterest = 0;
        $totalCost = 0;

        foreach ($loans as $loan) {
            $totalPrincipal += $loan->getPrincipal();
            $monthlyRate = $loan->getAnnualRate() / 12;
            $months = $loan->getMonths();
            
            if ($monthlyRate > 0) {
                $monthlyPayment = $loan->getPrincipal() * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
                $interest = ($monthlyPayment * $months) - $loan->getPrincipal();
            } else {
                $interest = 0;
            }
            
            $totalInterest += $interest;
            $totalCost += $loan->getPrincipal() + $interest;
        }

        return [
            'total_principal' => round($totalPrincipal, 2),
            'total_interest' => round($totalInterest, 2),
            'total_cost' => round($totalCost, 2),
            'profitability_ratio' => $totalPrincipal > 0 ? round($totalInterest / $totalPrincipal, 4) : 0.0,
            'profit_margin_percent' => $totalCost > 0 ? round(($totalInterest / $totalCost) * 100, 2) : 0.0
        ];
    }

    /**
     * Get average payment rate across portfolio
     */
    public function getAveragePaymentRate(array $loans): float {
        if (empty($loans)) {
            return 0.0;
        }

        $totalRate = 0.0;

        foreach ($loans as $loan) {
            $totalRate += $loan->getAnnualRate();
        }

        return round($totalRate / count($loans), 4);
    }

    /**
     * Analyze loan diversification across rates and terms
     */
    public function getLoanDiversification(array $loans): array {
        if (empty($loans)) {
            return [
                'rate_concentration' => 0.0,
                'term_concentration' => 0.0,
                'diversification_score' => 0.0
            ];
        }

        // Group by rate
        $rateGroups = [];
        foreach ($loans as $loan) {
            $rate = round($loan->getAnnualRate() * 100, 1);
            $rateGroups[$rate] = ($rateGroups[$rate] ?? 0) + 1;
        }

        // Group by term
        $termGroups = [];
        foreach ($loans as $loan) {
            $term = $loan->getMonths();
            $termGroups[$term] = ($termGroups[$term] ?? 0) + 1;
        }

        // Calculate concentration (Herfindahl index)
        $totalLoans = count($loans);
        $rateConcentration = 0;
        $termConcentration = 0;

        foreach ($rateGroups as $count) {
            $rateConcentration += pow($count / $totalLoans, 2);
        }

        foreach ($termGroups as $count) {
            $termConcentration += pow($count / $totalLoans, 2);
        }

        $diversificationScore = 1.0 - (($rateConcentration + $termConcentration) / 2);

        return [
            'rate_concentration' => round($rateConcentration, 4),
            'term_concentration' => round($termConcentration, 4),
            'diversification_score' => round($diversificationScore, 4)
        ];
    }

    /**
     * Analyze portfolio maturity distribution
     */
    public function analyzeLoanMaturity(array $loans): array {
        if (empty($loans)) {
            return [
                'current' => 0,
                'less_than_12_months' => 0,
                'less_than_5_years' => 0,
                'five_plus_years' => 0
            ];
        }

        $distribution = [
            'current' => 0,
            'less_than_12_months' => 0,
            'less_than_5_years' => 0,
            'five_plus_years' => 0
        ];

        foreach ($loans as $loan) {
            $remainingMonths = max(0, $loan->getMonths() - ($loan->getPaymentsMade() ?? 0));
            
            if ($remainingMonths == 0) {
                $distribution['current']++;
            } elseif ($remainingMonths < 12) {
                $distribution['less_than_12_months']++;
            } elseif ($remainingMonths < 60) {
                $distribution['less_than_5_years']++;
            } else {
                $distribution['five_plus_years']++;
            }
        }

        return $distribution;
    }

    /**
     * Get portfolio risk profile
     */
    public function getPortfolioRiskProfile(array $loans): array {
        if (empty($loans)) {
            return [
                'average_risk_score' => 0.0,
                'high_risk_count' => 0,
                'medium_risk_count' => 0,
                'low_risk_count' => 0,
                'portfolio_risk_level' => 'unknown'
            ];
        }

        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;
        $totalRiskScore = 0;

        foreach ($loans as $loan) {
            $rate = $loan->getAnnualRate();

            if ($rate > 0.08) {
                $highRisk++;
                $totalRiskScore += 75;
            } elseif ($rate > 0.05) {
                $mediumRisk++;
                $totalRiskScore += 50;
            } else {
                $lowRisk++;
                $totalRiskScore += 25;
            }
        }

        $avgRiskScore = round($totalRiskScore / count($loans), 2);
        
        if ($avgRiskScore >= 60) {
            $portfolioRiskLevel = 'high';
        } elseif ($avgRiskScore >= 40) {
            $portfolioRiskLevel = 'medium';
        } else {
            $portfolioRiskLevel = 'low';
        }

        return [
            'average_risk_score' => $avgRiskScore,
            'high_risk_count' => $highRisk,
            'medium_risk_count' => $mediumRisk,
            'low_risk_count' => $lowRisk,
            'portfolio_risk_level' => $portfolioRiskLevel
        ];
    }

    /**
     * Export portfolio report as array
     */
    public function exportPortfolioReport(array $loans): array {
        return [
            'total_loans' => count($loans),
            'total_principal' => round(array_sum(array_map(fn($l) => $l->getPrincipal(), $loans)), 2),
            'portfolio_yield' => $this->calculatePortfolioYield($loans),
            'default_rate' => $this->calculateDefaultRate($loans),
            'average_rate' => $this->getAveragePaymentRate($loans),
            'profitability' => $this->calculateProfitability($loans),
            'diversification' => $this->getLoanDiversification($loans),
            'maturity_distribution' => $this->analyzeLoanMaturity($loans),
            'risk_profile' => $this->getPortfolioRiskProfile($loans)
        ];
    }

    /**
     * Aggregate multiple portfolio metrics
     */
    public function aggregatePortfolioMetrics(array $portfolios): array {
        if (empty($portfolios)) {
            return [];
        }

        $allLoans = [];
        foreach ($portfolios as $portfolio) {
            $allLoans = array_merge($allLoans, $portfolio);
        }

        return $this->exportPortfolioReport($allLoans);
    }
}
