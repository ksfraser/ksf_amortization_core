<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use DateTimeImmutable;

class RefinancingAnalysisService
{
    public function calculateRefinancingBreakEven(Loan $existingLoan, array $refinanceOffer): array
    {
        $monthlyPaymentCurrent = $this->calculateMonthlyPayment(
            $existingLoan->getCurrentBalance(),
            $existingLoan->getAnnualRate() / 12,
            $existingLoan->getMonths() - $existingLoan->getPaymentsMade()
        );

        $monthlyPaymentNew = $this->calculateMonthlyPayment(
            $refinanceOffer['principal'],
            $refinanceOffer['rate'] / 12,
            $refinanceOffer['months']
        );

        $monthlySavings = $monthlyPaymentCurrent - $monthlyPaymentNew;

        if ($monthlySavings <= 0) {
            return [
                'break_even_months' => PHP_INT_MAX,
                'monthly_savings' => $monthlySavings,
                'closing_costs' => $refinanceOffer['closing_costs'],
            ];
        }

        $breakEvenMonths = ceil($refinanceOffer['closing_costs'] / $monthlySavings);

        return [
            'break_even_months' => $breakEvenMonths,
            'monthly_savings' => round($monthlySavings, 2),
            'closing_costs' => $refinanceOffer['closing_costs'],
            'total_loan_savings_at_payoff' => round(($monthlyPaymentCurrent - $monthlyPaymentNew) * $refinanceOffer['months'], 2),
        ];
    }

    public function calculateTotalSavingsWithRefinancing(Loan $existingLoan, array $refinanceOffer): array
    {
        $monthlyPaymentCurrent = $this->calculateMonthlyPayment(
            $existingLoan->getCurrentBalance(),
            $existingLoan->getAnnualRate() / 12,
            $existingLoan->getMonths() - $existingLoan->getPaymentsMade()
        );

        $interestRemainingCurrent = $this->calculateRemainingInterest($monthlyPaymentCurrent, $existingLoan);

        $monthlyPaymentNew = $this->calculateMonthlyPayment(
            $refinanceOffer['principal'],
            $refinanceOffer['rate'] / 12,
            $refinanceOffer['months']
        );

        $totalPaymentNew = $monthlyPaymentNew * $refinanceOffer['months'];
        $interestNew = $totalPaymentNew - $refinanceOffer['principal'];

        $totalInterestSavings = $interestRemainingCurrent - $interestNew;
        $netSavings = $totalInterestSavings - $refinanceOffer['closing_costs'];

        return [
            'total_interest_savings' => round($totalInterestSavings, 2),
            'refinancing_costs' => $refinanceOffer['closing_costs'],
            'net_savings' => round($netSavings, 2),
            'current_remaining_interest' => round($interestRemainingCurrent, 2),
            'new_total_interest' => round($interestNew, 2),
        ];
    }

    public function compareRefinancingOffers(Loan $existingLoan, array $offers): array
    {
        $comparison = [];

        foreach ($offers as $index => $offer) {
            $savings = $this->calculateTotalSavingsWithRefinancing($existingLoan, $offer);
            $breakEven = $this->calculateRefinancingBreakEven($existingLoan, $offer);
            $payment = $this->estimateMonthlyPaymentAfterRefinancing($offer);

            $comparison[] = [
                'offer_index' => $index,
                'rate' => $offer['rate'],
                'months' => $offer['months'],
                'monthly_payment' => round($payment, 2),
                'closing_costs' => $offer['closing_costs'],
                'net_savings' => $savings['net_savings'],
                'break_even_months' => $breakEven['break_even_months'],
                'monthly_savings' => $breakEven['monthly_savings'],
            ];
        }

        usort($comparison, function($a, $b) {
            if ($a['net_savings'] == $b['net_savings']) return 0;
            return ($a['net_savings'] < $b['net_savings']) ? 1 : -1;
        });

        foreach ($comparison as $index => &$offer) {
            $offer['rank'] = $index + 1;
        }

        return $comparison;
    }

    public function calculatePayoffTimelineChange(Loan $existingLoan, array $refinanceOffer): array
    {
        $remainingMonths = $existingLoan->getMonths() - $existingLoan->getPaymentsMade();
        $startDate = $existingLoan->getStartDate();
        $currentPayoffDate = $startDate->add(new \DateInterval('P' . $existingLoan->getMonths() . 'M'));

        $newPayoffDate = $startDate->add(new \DateInterval('P' . ($existingLoan->getPaymentsMade() + $refinanceOffer['months']) . 'M'));

        $monthsAccelerated = $remainingMonths - $refinanceOffer['months'];

        return [
            'current_payoff_date' => $currentPayoffDate->format('Y-m-d'),
            'new_payoff_date' => $newPayoffDate->format('Y-m-d'),
            'months_accelerated' => max(0, -$monthsAccelerated),
            'months_extended' => max(0, $monthsAccelerated),
        ];
    }

    public function generateRefinancingRecommendation(Loan $existingLoan, array $offers, string $goal): array
    {
        $comparison = $this->compareRefinancingOffers($existingLoan, $offers);

        $recommended = $comparison[0];

        $reasoning = match ($goal) {
            'maximize_savings' => "This offer provides the maximum net savings of \${$recommended['net_savings']} after closing costs.",
            'minimize_payment' => "This offer reduces your monthly payment to \${$recommended['monthly_payment']}, saving you \${$recommended['monthly_savings']}/month.",
            'accelerate_payoff' => "This offer reduces your loan term while maintaining manageable payments.",
            default => "This offer provides the best overall value.",
        };

        return [
            'recommended_offer' => $recommended,
            'reason' => $goal,
            'reasoning' => $reasoning,
            'alternative_offers' => array_slice($comparison, 1, 2),
        ];
    }

    public function analyzeRefinancingROI(Loan $existingLoan, array $refinanceOffer, int $yearsToAnalyze): array
    {
        $breakEven = $this->calculateRefinancingBreakEven($existingLoan, $refinanceOffer);
        $savings = $this->calculateTotalSavingsWithRefinancing($existingLoan, $refinanceOffer);

        $totalMonthsAnalyzed = $yearsToAnalyze * 12;
        $totalSavingsInPeriod = ($breakEven['monthly_savings'] * $totalMonthsAnalyzed) - $refinanceOffer['closing_costs'];

        $roi = ($totalSavingsInPeriod / $refinanceOffer['closing_costs']) * 100;

        return [
            'roi_percentage' => round($roi, 2),
            'payback_period' => round($breakEven['break_even_months'] / 12, 1),
            'total_savings_in_period' => round($totalSavingsInPeriod, 2),
            'analysis_period_years' => $yearsToAnalyze,
        ];
    }

    public function calculateCreditScoreImpact(Loan $existingLoan): array
    {
        return [
            'hard_inquiry_impact' => -5,
            'new_account_impact' => -15,
            'average_age_impact' => -5,
            'total_temporary_impact' => -25,
            'credit_utilization_impact' => 0,
            'recovery_timeline_months' => 6,
        ];
    }

    public function estimateMonthlyPaymentAfterRefinancing(array $refinanceOffer): float
    {
        return $this->calculateMonthlyPayment(
            $refinanceOffer['principal'],
            $refinanceOffer['rate'] / 12,
            $refinanceOffer['months']
        );
    }

    public function identifyRefinancingOpportunities(Loan $existingLoan, float $marketRate): array
    {
        $currentRate = $existingLoan->getAnnualRate();
        $rateDifference = $currentRate - $marketRate;

        $shouldRefinance = $rateDifference > 0.005;

        $savingsPotential = 0;
        if ($shouldRefinance) {
            $monthlyPayment = $this->calculateMonthlyPayment(
                $existingLoan->getCurrentBalance(),
                $marketRate / 12,
                $existingLoan->getMonths() - $existingLoan->getPaymentsMade()
            );

            $currentMonthly = $this->calculateMonthlyPayment(
                $existingLoan->getCurrentBalance(),
                $currentRate / 12,
                $existingLoan->getMonths() - $existingLoan->getPaymentsMade()
            );

            $savingsPotential = round(($currentMonthly - $monthlyPayment) * 360, 2);
        }

        return [
            'should_refinance' => $shouldRefinance,
            'current_rate' => $currentRate,
            'market_rate' => $marketRate,
            'rate_difference' => round($rateDifference, 4),
            'savings_potential' => $savingsPotential,
        ];
    }

    public function calculateRefinancingCashOutOptions(Loan $existingLoan, float $homeValue, float $rate): array
    {
        $loanAmountAt80LTV = $homeValue * 0.80;
        $currentBalance = $existingLoan->getCurrentBalance();
        $maxCashOut = $loanAmountAt80LTV - $currentBalance;

        return [
            'max_cash_out' => round(max(0, $maxCashOut), 2),
            'home_value' => $homeValue,
            'loan_amount_at_80ltv' => round($loanAmountAt80LTV, 2),
            'current_balance' => $currentBalance,
            'current_equity' => round($homeValue - $currentBalance, 2),
        ];
    }

    public function simulateRefinancingScenarios(Loan $existingLoan, array $rates, array $terms): array
    {
        $scenarios = [];

        foreach ($rates as $rate) {
            foreach ($terms as $term) {
                $scenarios[] = [
                    'rate' => $rate,
                    'months' => $term,
                    'monthly_payment' => round($this->calculateMonthlyPayment($existingLoan->getCurrentBalance(), $rate / 12, $term), 2),
                    'total_interest' => round($this->calculateMonthlyPayment($existingLoan->getCurrentBalance(), $rate / 12, $term) * $term - $existingLoan->getCurrentBalance(), 2),
                ];
            }
        }

        return $scenarios;
    }

    public function generateRefinancingReport(Loan $existingLoan, array $offers): array
    {
        $comparison = $this->compareRefinancingOffers($existingLoan, $offers);
        $topOffer = $comparison[0];

        return [
            'summary' => [
                'current_balance' => $existingLoan->getCurrentBalance(),
                'current_rate' => $existingLoan->getAnnualRate(),
                'months_remaining' => $existingLoan->getMonths() - $existingLoan->getPaymentsMade(),
            ],
            'analysis' => $comparison,
            'recommendations' => [
                'top_recommendation' => $topOffer,
                'potential_monthly_savings' => $topOffer['monthly_savings'],
                'break_even_period' => $topOffer['break_even_months'],
            ],
        ];
    }

    public function calculateTaxImplicationsOfRefinancing(Loan $existingLoan, array $refinanceOffer): array
    {
        $currentDeductionRate = 0.24;
        $remainingMonths = $existingLoan->getMonths() - $existingLoan->getPaymentsMade();

        $monthlyPaymentCurrent = $this->calculateMonthlyPayment(
            $existingLoan->getCurrentBalance(),
            $existingLoan->getAnnualRate() / 12,
            $remainingMonths
        );

        $interestRemainingCurrent = ($monthlyPaymentCurrent * $remainingMonths) - $existingLoan->getCurrentBalance();

        $monthlyPaymentNew = $this->calculateMonthlyPayment(
            $refinanceOffer['principal'],
            $refinanceOffer['rate'] / 12,
            $refinanceOffer['months']
        );

        $interestNew = ($monthlyPaymentNew * $refinanceOffer['months']) - $refinanceOffer['principal'];

        $lostDeductionValue = round($interestRemainingCurrent * $currentDeductionRate, 2);
        $newDeductionValue = round($interestNew * $currentDeductionRate, 2);

        return [
            'lost_deduction_value' => $lostDeductionValue,
            'new_deduction_value' => $newDeductionValue,
            'deduction_change' => round($newDeductionValue - $lostDeductionValue, 2),
            'tax_bracket_assumed' => 24,
        ];
    }

    public function exportRefinancingAnalysisToJSON(Loan $existingLoan, array $refinanceOffer): string
    {
        $breakEven = $this->calculateRefinancingBreakEven($existingLoan, $refinanceOffer);
        $savings = $this->calculateTotalSavingsWithRefinancing($existingLoan, $refinanceOffer);
        $timeline = $this->calculatePayoffTimelineChange($existingLoan, $refinanceOffer);

        return json_encode([
            'existing_loan' => [
                'balance' => $existingLoan->getCurrentBalance(),
                'rate' => $existingLoan->getAnnualRate(),
                'months_remaining' => $existingLoan->getMonths() - $existingLoan->getPaymentsMade(),
            ],
            'refinance_offer' => $refinanceOffer,
            'break_even_analysis' => $breakEven,
            'savings_analysis' => $savings,
            'timeline_change' => $timeline,
            'export_date' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function calculateMonthlyPayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate == 0) {
            return $principal / $months;
        }
        return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }

    private function calculateRemainingInterest(float $monthlyPayment, Loan $loan): float
    {
        $remainingMonths = $loan->getMonths() - $loan->getPaymentsMade();
        return ($monthlyPayment * $remainingMonths) - $loan->getCurrentBalance();
    }
}
