<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

class PredictiveAnalyticsService
{
    public function forecastLoanPerformance(Loan $loan, int $months): array
    {
        $forecast = [];
        $balance = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;

        for ($i = 0; $i < $months; $i++) {
            $forecast[] = [
                'month' => $i + 1,
                'predicted_balance' => round($balance, 2),
                'confidence' => 95 - ($i * 0.5),
            ];
            $balance = $balance * (1 - 0.02);
        }

        return $forecast;
    }

    public function calculateDefaultRiskScore(Loan $loan): array
    {
        $baseScore = 100;
        $riskFactors = 0;

        if ($loan->getAnnualRate() > 0.08) $riskFactors += 15;
        if ($loan->getMonths() > 360) $riskFactors += 10;

        $riskScore = max(0, $baseScore - $riskFactors);

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskScore > 80 ? 'low' : ($riskScore > 50 ? 'medium' : 'high'),
            'factors' => $riskFactors,
        ];
    }

    public function estimatePrepaymentProbability(Loan $loan): array
    {
        $probability = 0.35;
        if ($loan->getAnnualRate() < 0.04) {
            $probability = 0.15;
        } elseif ($loan->getAnnualRate() > 0.06) {
            $probability = 0.55;
        }

        return [
            'prepayment_probability' => $probability,
            'confidence' => 0.72,
            'expected_prepayment_month' => round($loan->getMonths() * $probability),
        ];
    }

    public function predictPaymentBehavior(Loan $loan): array
    {
        return [
            'on_time_probability' => 0.95,
            'late_probability' => 0.04,
            'default_probability' => 0.01,
            'forecast_period_months' => 12,
        ];
    }

    public function trendAnalysis(Loan $loan, array $historicalData): array
    {
        if (empty($historicalData)) {
            return ['trend' => 'insufficient_data'];
        }

        $trend = count($historicalData) > 1 && $historicalData[0] > $historicalData[1] ? 'declining' : 'stable';

        return [
            'trend' => $trend,
            'data_points' => count($historicalData),
            'analysis_confidence' => 0.85,
        ];
    }

    public function generateRiskAssessmentReport(Loan $loan): array
    {
        $riskScore = $this->calculateDefaultRiskScore($loan);
        $prepayment = $this->estimatePrepaymentProbability($loan);
        $payment = $this->predictPaymentBehavior($loan);

        return [
            'loan_id' => $loan->getId(),
            'risk_assessment' => $riskScore,
            'prepayment_analysis' => $prepayment,
            'payment_behavior' => $payment,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function simulateScenarios(Loan $loan, array $rateScenarios): array
    {
        $scenarios = [];

        foreach ($rateScenarios as $rate) {
            $scenarios[] = [
                'rate' => $rate,
                'monthly_payment' => $this->calculateMonthlyPayment(
                    $loan->getCurrentBalance(),
                    $rate / 12,
                    $loan->getMonths() - $loan->getPaymentsMade()
                ),
                'payoff_date' => date('Y-m-d', strtotime('+' . ($loan->getMonths() - $loan->getPaymentsMade()) . ' months')),
            ];
        }

        return $scenarios;
    }

    public function exportPredictiveAnalysis(Loan $loan): string
    {
        return json_encode([
            'loan_id' => $loan->getId(),
            'risk_score' => $this->calculateDefaultRiskScore($loan),
            'prepayment_probability' => $this->estimatePrepaymentProbability($loan),
            'export_date' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT);
    }

    private function calculateMonthlyPayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate == 0) {
            return $principal / $months;
        }
        return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }
}
