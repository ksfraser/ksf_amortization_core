<?php
namespace Ksfraser\Amortizations\Services;

class MarketAnalysisService {

    /**
     * Get current market rates for different loan types
     */
    public function getMarketRates(): array {
        return [
            'auto_15_year' => 0.049,
            'auto_20_year' => 0.052,
            'mortgage_15_year' => 0.055,
            'mortgage_30_year' => 0.062,
            'personal_unsecured' => 0.085,
            'personal_secured' => 0.065
        ];
    }

    /**
     * Compare loan rate to market average
     */
    public function compareToMarketAverage(float $loanRate, string $loanType = 'mortgage_30_year'): array {
        $marketRates = $this->getMarketRates();
        $marketRate = $marketRates[$loanType] ?? 0.06;

        $difference = $loanRate - $marketRate;
        $percentDifference = ($difference / $marketRate) * 100;

        return [
            'loan_rate' => round($loanRate * 100, 2),
            'market_rate' => round($marketRate * 100, 2),
            'difference_bps' => round($difference * 10000, 0),
            'percent_difference' => round($percentDifference, 2),
            'competitive' => $difference <= 0.01
        ];
    }

    /**
     * Rank rate competitiveness
     */
    public function rankRateCompetitiveness(float $loanRate, array $competitorRates): array {
        $allRates = array_merge([$loanRate], $competitorRates);
        sort($allRates);

        $position = array_search($loanRate, $allRates) + 1;
        $percentile = round(($position / count($allRates)) * 100, 1);

        return [
            'our_rate' => round($loanRate * 100, 2),
            'lowest_rate' => round(min($allRates) * 100, 2),
            'highest_rate' => round(max($allRates) * 100, 2),
            'average_rate' => round(array_sum($allRates) / count($allRates) * 100, 2),
            'rank' => $position,
            'percentile' => $percentile,
            'competitive_position' => $percentile <= 50 ? 'above_average' : 'below_average'
        ];
    }

    /**
     * Analyze rate trend direction
     */
    public function analyzeTrendDirection(array $historicalRates): array {
        if (count($historicalRates) < 2) {
            return ['trend' => 'insufficient_data', 'strength' => 0];
        }

        $latest = end($historicalRates);
        $previous = prev($historicalRates);
        $oldest = reset($historicalRates);

        $recentChange = $latest - $previous;
        $overallChange = $latest - $oldest;
        $overallTrend = $overallChange > 0 ? 'increasing' : ($overallChange < 0 ? 'decreasing' : 'stable');

        return [
            'trend' => $overallTrend,
            'recent_change_bps' => round($recentChange * 10000, 0),
            'overall_change_bps' => round($overallChange * 10000, 0),
            'volatility' => round(($this->calculateStandardDeviation($historicalRates)) * 100, 2)
        ];
    }

    /**
     * Forecast future rate movements
     */
    public function forecastRateMovement(array $historicalRates, int $forecastMonths = 3): array {
        if (empty($historicalRates)) {
            return ['forecast' => [], 'confidence' => 0];
        }

        $average = array_sum($historicalRates) / count($historicalRates);
        $trend = end($historicalRates) > reset($historicalRates) ? 0.0001 : -0.0001;

        $forecast = [];
        $currentRate = end($historicalRates);

        for ($i = 1; $i <= $forecastMonths; $i++) {
            $forecastedRate = $currentRate + ($trend * $i);
            $forecast['month_' . $i] = round($forecastedRate, 4);
        }

        return [
            'forecast' => $forecast,
            'confidence' => 60,
            'method' => 'simple_linear_extrapolation'
        ];
    }

    /**
     * Identify arbitrage opportunities
     */
    public function identifyArbitrage(array $borrowingRates, array $lendingRates): array {
        $opportunities = [];

        foreach ($borrowingRates as $type => $borrowRate) {
            if (isset($lendingRates[$type])) {
                $spread = $lendingRates[$type] - $borrowRate;
                if ($spread > 0.01) {
                    $opportunities[] = [
                        'type' => $type,
                        'borrow_rate' => round($borrowRate * 100, 2),
                        'lend_rate' => round($lendingRates[$type] * 100, 2),
                        'spread_bps' => round($spread * 10000, 0),
                        'opportunity_strength' => $spread > 0.03 ? 'high' : 'medium'
                    ];
                }
            }
        }

        return ['arbitrage_opportunities' => $opportunities];
    }

    /**
     * Suggest rate optimization strategy
     */
    public function suggestRateOptimization(float $currentRate, float $marketRate, array $competitorRates): array {
        $comparison = $this->rankRateCompetitiveness($currentRate, $competitorRates);

        if ($currentRate > $marketRate + 0.01) {
            return [
                'recommendation' => 'reduce_rate',
                'suggested_rate' => round($marketRate, 4),
                'expected_impact' => 'increase_competitiveness',
                'rationale' => 'Your rate is above market average'
            ];
        } elseif ($currentRate < $marketRate - 0.005) {
            return [
                'recommendation' => 'increase_rate',
                'suggested_rate' => round($marketRate - 0.003, 4),
                'expected_impact' => 'improve_margins',
                'rationale' => 'Your rate is significantly below market'
            ];
        } else {
            return [
                'recommendation' => 'maintain_rate',
                'suggested_rate' => $currentRate,
                'expected_impact' => 'stable',
                'rationale' => 'Your rate is competitive'
            ];
        }
    }

    /**
     * Calculate market share estimation
     */
    public function calculateMarketShare(float $ourLoanVolume, float $totalMarketVolume): array {
        $marketShare = ($ourLoanVolume / $totalMarketVolume) * 100;

        return [
            'our_volume' => round($ourLoanVolume, 2),
            'total_market_volume' => round($totalMarketVolume, 2),
            'market_share_percent' => round($marketShare, 2),
            'market_share_rank' => $marketShare > 5 ? 'significant' : ($marketShare > 1 ? 'moderate' : 'small')
        ];
    }

    /**
     * Analyze lender comparison
     */
    public function analyzeLenderComparison(array $lenders): array {
        if (empty($lenders)) {
            return ['analysis' => 'no_data'];
        }

        $avgRate = array_sum(array_map(fn($l) => $l['rate'], $lenders)) / count($lenders);
        $minRate = min(array_map(fn($l) => $l['rate'], $lenders));
        $maxRate = max(array_map(fn($l) => $l['rate'], $lenders));

        return [
            'total_lenders' => count($lenders),
            'average_rate' => round($avgRate * 100, 2),
            'lowest_rate' => round($minRate * 100, 2),
            'highest_rate' => round($maxRate * 100, 2),
            'rate_spread' => round(($maxRate - $minRate) * 100, 2),
            'market_concentration' => count($lenders) > 10 ? 'fragmented' : 'concentrated'
        ];
    }

    /**
     * Identify market opportunities
     */
    public function identifyMarketOpportunities(float $currentRate, array $demographics): array {
        $opportunities = [];

        if (isset($demographics['age_group'])) {
            if ($demographics['age_group'] === 'young_professionals' && $currentRate > 0.065) {
                $opportunities[] = 'Reduce rate for young professionals segment';
            }
        }

        if (isset($demographics['credit_score'])) {
            if ($demographics['credit_score'] > 750 && $currentRate > 0.045) {
                $opportunities[] = 'Premium rate for excellent credit customers';
            }
        }

        return ['opportunities' => $opportunities];
    }

    /**
     * Generate market report
     */
    public function generateMarketReport(float $ourRate, array $historicalRates, array $competitors): array {
        return [
            'report_date' => date('Y-m-d'),
            'our_current_rate' => round($ourRate * 100, 2),
            'market_trend' => $this->analyzeTrendDirection($historicalRates),
            'competitive_analysis' => $this->rankRateCompetitiveness($ourRate, array_map(fn($c) => $c['rate'], $competitors)),
            'lender_analysis' => $this->analyzeLenderComparison($competitors),
            'forecast' => $this->forecastRateMovement($historicalRates)
        ];
    }

    /**
     * Create rate forecast
     */
    public function createRateForecast(array $historicalRates, int $months = 6): array {
        $forecast = $this->forecastRateMovement($historicalRates, $months);
        
        return [
            'forecast_period_months' => $months,
            'forecast_data' => $forecast['forecast'],
            'confidence_level' => $forecast['confidence'],
            'last_historical_rate' => round(end($historicalRates) * 100, 2),
            'forecast_methodology' => $forecast['method']
        ];
    }

    /**
     * Optimize rate strategy
     */
    public function optimizeRateStrategy(float $currentRate, float $targetMargin, array $marketRates): array {
        $marketAvg = array_sum($marketRates) / count($marketRates);
        $optimalRate = $marketAvg + $targetMargin;

        return [
            'current_rate' => round($currentRate * 100, 2),
            'market_average' => round($marketAvg * 100, 2),
            'target_margin_bps' => round($targetMargin * 10000, 0),
            'optimal_rate' => round($optimalRate * 100, 2),
            'adjustment_needed' => round(($optimalRate - $currentRate) * 10000, 0) . ' bps'
        ];
    }

    /**
     * Export market analysis
     */
    public function exportMarketAnalysis(array $analysis): string {
        return json_encode($analysis, JSON_PRETTY_PRINT);
    }

    private function calculateStandardDeviation(array $values): float {
        if (empty($values)) {
            return 0.0;
        }

        $avg = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $avg, 2), $values)) / count($values);
        return sqrt($variance);
    }
}
