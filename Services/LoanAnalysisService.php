<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

class LoanAnalysisService {
    public function calculateLoanToValueRatio(Loan $loan, float $propertyValue): float {
        if ($propertyValue <= 0) {
            throw new \InvalidArgumentException("Property value must be greater than zero");
        }
        return round($loan->getPrincipal() / $propertyValue, 4);
    }

    public function calculateDebtToIncomeRatio(Loan $loan, float $monthlyIncome, float $otherMonthlyDebts = 0): float {
        if ($monthlyIncome <= 0) {
            throw new \InvalidArgumentException("Monthly income must be greater than zero");
        }
        $monthlyPayment = $this->estimateMonthlyPayment($loan);
        $totalMonthlyDebt = $monthlyPayment + $otherMonthlyDebts;
        return round($totalMonthlyDebt / $monthlyIncome, 4);
    }

    public function calculateCreditworthinessScore(Loan $loan, float $creditScore = 750, float $debtToIncomeRatio = 0.43, float $employmentYears = 5): array {
        $score = 0;
        $factors = [];

        // Credit score impact (0-300 points)
        $creditFactor = min(300, ($creditScore - 300) / 5.5);
        $score += max(0, $creditFactor);
        $factors['credit_score_factor'] = round($creditFactor, 2);

        // DTI ratio impact (0-250 points)
        $dtiFactor = max(0, 250 - ($debtToIncomeRatio * 400));
        $score += $dtiFactor;
        $factors['dti_factor'] = round($dtiFactor, 2);

        // Employment stability (0-200 points)
        $employmentFactor = min(200, $employmentYears * 25);
        $score += $employmentFactor;
        $factors['employment_factor'] = round($employmentFactor, 2);

        // Loan amount vs income (0-250 points)
        $loanToIncomeMonths = $loan->getPrincipal() / (max($loan->getPrincipal() / $loan->getMonths(), 1000));
        $loanFactor = max(0, 250 - ($loanToIncomeMonths * 10));
        $score += $loanFactor;
        $factors['loan_factor'] = round($loanFactor, 2);

        $totalScore = round($score, 2);
        return [
            'creditworthiness_score' => $totalScore,
            'max_score' => 1000,
            'percentage' => round(($totalScore / 1000) * 100, 2),
            'factors' => $factors
        ];
    }

    public function assessLoanRisk(Loan $loan, float $creditScore = 750): array {
        $riskFactors = [];
        $riskLevel = 'low';
        $riskScore = 0;

        // Interest rate risk (higher rates = higher risk)
        if ($loan->getAnnualRate() > 0.08) {
            $riskScore += 30;
            $riskFactors['high_interest_rate'] = true;
        } elseif ($loan->getAnnualRate() > 0.05) {
            $riskScore += 15;
        }

        // Loan term risk (longer terms = higher default risk)
        if ($loan->getMonths() > 360) {
            $riskScore += 25;
            $riskFactors['long_term'] = true;
        }

        // Credit score risk
        if ($creditScore < 620) {
            $riskScore += 40;
            $riskFactors['poor_credit'] = true;
        } elseif ($creditScore < 700) {
            $riskScore += 25;
            $riskFactors['fair_credit'] = true;
        }

        // Balloon payment risk
        if ($loan->hasBalloonPayment()) {
            $riskScore += 20;
            $riskFactors['balloon_payment'] = true;
        }

        // Determine risk level
        if ($riskScore >= 75) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 50) {
            $riskLevel = 'medium';
        }

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'max_risk_score' => 100,
            'risk_factors' => $riskFactors,
            'default_probability' => round(($riskScore / 100) * 0.15, 4)
        ];
    }

    public function analyzeAfforcability(Loan $loan, float $monthlyIncome, float $otherDebts = 0, float $savings = 0): array {
        $monthlyPayment = $this->estimateMonthlyPayment($loan);
        $totalDebt = $monthlyPayment + $otherDebts;
        $dti = $totalDebt / $monthlyIncome;
        $isAffordable = $dti <= 0.43;

        $savingsRatio = $savings > 0 ? $monthlyPayment / $savings : 0;

        return [
            'is_affordable' => $isAffordable,
            'monthly_payment' => round($monthlyPayment, 2),
            'monthly_income' => $monthlyIncome,
            'total_monthly_debt' => round($totalDebt, 2),
            'dti_ratio' => round($dti, 4),
            'max_dti_ratio' => 0.43,
            'savings_coverage_months' => $savingsRatio > 0 ? round($savings / $monthlyPayment, 2) : 0,
            'recommendation' => $isAffordable ? 'approved' : 'denied'
        ];
    }

    public function compareLoans(Loan $loan1, Loan $loan2): array {
        $payment1 = $this->estimateMonthlyPayment($loan1);
        $interest1 = ($payment1 * $loan1->getMonths()) - $loan1->getPrincipal();
        
        $payment2 = $this->estimateMonthlyPayment($loan2);
        $interest2 = ($payment2 * $loan2->getMonths()) - $loan2->getPrincipal();

        return [
            'loan1' => [
                'monthly_payment' => round($payment1, 2),
                'total_interest' => round($interest1, 2),
                'total_cost' => round($loan1->getPrincipal() + $interest1, 2),
                'effective_rate' => round($loan1->getAnnualRate() * 100, 2) . '%'
            ],
            'loan2' => [
                'monthly_payment' => round($payment2, 2),
                'total_interest' => round($interest2, 2),
                'total_cost' => round($loan2->getPrincipal() + $interest2, 2),
                'effective_rate' => round($loan2->getAnnualRate() * 100, 2) . '%'
            ],
            'better_option' => $interest1 < $interest2 ? 'loan1' : 'loan2',
            'savings' => round(abs($interest1 - $interest2), 2),
            'monthly_savings' => round(abs($payment1 - $payment2), 2)
        ];
    }

    public function generateLoanQualificationReport(Loan $loan, float $monthlyIncome, float $creditScore = 750, float $propertyValue = 0, float $otherDebts = 0): array {
        $analysis = [
            'loan_amount' => $loan->getPrincipal(),
            'interest_rate' => round($loan->getAnnualRate() * 100, 2) . '%',
            'monthly_payment' => round($this->estimateMonthlyPayment($loan), 2),
            'total_term_months' => $loan->getMonths(),
        ];

        // LTV if property value provided
        if ($propertyValue > 0) {
            $analysis['loan_to_value_ratio'] = round($this->calculateLoanToValueRatio($loan, $propertyValue), 4);
        }

        // DTI analysis
        $dti = $this->calculateDebtToIncomeRatio($loan, $monthlyIncome, $otherDebts);
        $analysis['debt_to_income_ratio'] = $dti;
        $analysis['dti_qualified'] = $dti <= 0.43;

        // Creditworthiness
        $creditworthiness = $this->calculateCreditworthinessScore($loan, $creditScore);
        $analysis['creditworthiness_score'] = $creditworthiness['creditworthiness_score'];
        $analysis['creditworthiness_percentage'] = $creditworthiness['percentage'];

        // Risk assessment
        $riskAssessment = $this->assessLoanRisk($loan, $creditScore);
        $analysis['risk_level'] = $riskAssessment['risk_level'];
        $analysis['default_probability'] = $riskAssessment['default_probability'];

        // Affordability
        $affordability = $this->analyzeAfforcability($loan, $monthlyIncome, $otherDebts);
        $analysis['is_affordable'] = $affordability['is_affordable'];
        $analysis['savings_coverage_months'] = $affordability['savings_coverage_months'];

        // Overall recommendation
        $qualifies = $dti <= 0.43 && $creditworthiness['creditworthiness_score'] >= 600 && $affordability['is_affordable'];
        $analysis['recommendation'] = $qualifies ? 'qualified' : 'not_qualified';
        $analysis['qualification_reasons'] = [];

        if (!($dti <= 0.43)) {
            $analysis['qualification_reasons'][] = "DTI ratio exceeds 43%";
        }
        if ($creditworthiness['creditworthiness_score'] < 600) {
            $analysis['qualification_reasons'][] = "Creditworthiness score below 600";
        }
        if (!$affordability['is_affordable']) {
            $analysis['qualification_reasons'][] = "Loan not affordable based on income";
        }

        return $analysis;
    }

    public function calculateMaxLoanAmount(float $monthlyIncome, float $creditScore = 750, float $maxDtiRatio = 0.43, float $interestRate = 0.05, int $months = 360): float {
        $maxMonthlyDebt = $monthlyIncome * $maxDtiRatio;
        $monthlyRate = $interestRate / 12;

        if ($monthlyRate == 0) {
            return $maxMonthlyDebt * $months;
        }

        // Reverse amortization formula to find principal
        $numerator = $maxMonthlyDebt * (pow(1 + $monthlyRate, $months) - 1);
        $denominator = $monthlyRate * pow(1 + $monthlyRate, $months);

        return round($numerator / $denominator, 2);
    }

    private function estimateMonthlyPayment(Loan $loan): float {
        $principal = $loan->getPrincipal();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $months = $loan->getMonths();

        if ($monthlyRate == 0) {
            return $principal / $months;
        }

        return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }

    public function exportAnalysisToJSON(Loan $loan, float $monthlyIncome, float $creditScore = 750): string {
        $report = $this->generateLoanQualificationReport($loan, $monthlyIncome, $creditScore);
        return json_encode($report, JSON_PRETTY_PRINT);
    }
}
