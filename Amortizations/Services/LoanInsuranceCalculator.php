<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use DateTimeImmutable;

/**
 * LoanInsuranceCalculator - Loan Insurance & PMI Management
 *
 * Manages insurance products on loans including Private Mortgage Insurance (PMI),
 * credit insurance, and other coverage types. Handles premium calculations,
 * LTV tracking, automatic and manual cancellation triggers, and cost reporting.
 *
 * Key Features:
 * - Add multiple insurance policies to loans
 * - Calculate monthly and total insurance premiums
 * - Calculate LTV (Loan-to-Value) ratios
 * - Automatic PMI cancellation at 80% LTV
 * - Manual cancellation requests
 * - Premium payment tracking and reporting
 * - Support for fixed-term and LTV-based policies
 *
 * Insurance Types Supported:
 * - PMI: Private Mortgage Insurance (LTV-based cancellation)
 * - Credit Insurance: Fixed term or condition-based
 * - Loan Protection: Optional coverage
 */
class LoanInsuranceCalculator
{
    private $policies = [];
    private $payments = [];

    /**
     * Add insurance policy to loan
     *
     * @param Loan $loan
     * @param string $insuranceType (PMI|Credit_Insurance|Loan_Protection)
     * @param float $annualRate Annual premium as percentage (0.005 = 0.5%)
     * @param int|null $termMonths Fixed term or null for LTV-based
     * @return array Insurance policy configuration
     */
    public function addInsurancePolicy(
        Loan $loan,
        string $insuranceType,
        float $annualRate,
        ?int $termMonths = null
    ): array {
        $policy = [
            'policy_id' => uniqid('polic_'),
            'loan_id' => $loan->getId(),
            'insurance_type' => $insuranceType,
            'annual_rate' => $annualRate,
            'term_months' => $termMonths,
            'effective_date' => date('Y-m-d'),
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->policies[$policy['policy_id']] = $policy;
        $this->payments[$policy['policy_id']] = [];

        return $policy;
    }

    /**
     * Calculate monthly insurance premium
     *
     * @param Loan $loan
     * @param array $policy
     * @return float Monthly premium amount
     */
    public function calculateMonthlyPremium(Loan $loan, array $policy): float
    {
        $annualPremium = $loan->getCurrentBalance() * $policy['annual_rate'];
        $monthlyPremium = $annualPremium / 12;

        return round($monthlyPremium, 2);
    }

    /**
     * Determine if PMI is required based on down payment
     *
     * @param Loan $loan
     * @param float $downPayment Amount of down payment
     * @return bool PMI required (< 20% down payment)
     */
    public function isPMIRequired(Loan $loan, float $downPayment): bool
    {
        $loanAmount = $loan->getPrincipal();
        $totalPrice = $loanAmount + $downPayment;

        $downPaymentPercent = $downPayment / $totalPrice;

        // PMI required if down payment < 20%
        return $downPaymentPercent < 0.20;
    }

    /**
     * Calculate Loan-to-Value (LTV) ratio
     *
     * LTV = Loan Amount / Property Value
     *
     * @param Loan $loan
     * @param float $propertyValue Current property value
     * @return float LTV ratio (0.80 = 80%)
     */
    public function calculateLTV(Loan $loan, float $propertyValue): float
    {
        if ($propertyValue <= 0) {
            return 1.0;
        }

        $ltv = $loan->getCurrentBalance() / $propertyValue;
        return round($ltv, 2);
    }

    /**
     * Determine if PMI cancellation is eligible
     *
     * Standard: 80% LTV or lower
     *
     * @param float $ltv Loan-to-Value ratio
     * @return bool Eligible for cancellation
     */
    public function isPMICancellationEligible(float $ltv): bool
    {
        return $ltv <= 0.80;
    }

    /**
     * Apply cancellation trigger to insurance policy
     *
     * @param Loan $loan
     * @param array $policy
     * @param float|null $propertyValue For LTV calculation (automatic)
     * @param string $triggerType (automatic|manual)
     * @param string|null $reason Cancellation reason
     * @return bool Cancellation successful
     */
    public function applyCancellationTrigger(
        Loan $loan,
        array &$policy,
        ?float $propertyValue = null,
        string $triggerType = 'manual',
        ?string $reason = null
    ): bool {
        // Verify eligibility for automatic triggers
        if ($triggerType === 'automatic' && $propertyValue) {
            $ltv = $this->calculateLTV($loan, $propertyValue);
            if (!$this->isPMICancellationEligible($ltv)) {
                return false;
            }
        }

        $policy['status'] = 'CANCELLED';
        $policy['cancellation_date'] = date('Y-m-d');
        $policy['cancellation_reason'] = $reason ?? $triggerType;

        return true;
    }

    /**
     * Calculate total insurance cost over loan term
     *
     * @param Loan $loan
     * @param array $policy
     * @return float Total insurance cost
     */
    public function calculateTotalInsuranceCost(Loan $loan, array $policy): float
    {
        $monthlyPremium = $this->calculateMonthlyPremium($loan, $policy);
        
        // Determine effective months
        $totalMonths = $loan->getMonths();
        if (isset($policy['cancellation_month'])) {
            $totalMonths = min($policy['cancellation_month'], $totalMonths);
        }

        $totalCost = $monthlyPremium * $totalMonths;
        return round($totalCost, 2);
    }

    /**
     * Record insurance premium payment
     *
     * @param int $loanId
     * @param string $policyId
     * @param float $amount Premium amount paid
     * @param string $paymentDate Payment date (YYYY-MM-DD)
     * @return bool Recording successful
     */
    public function recordInsurancePayment(
        int $loanId,
        string $policyId,
        float $amount,
        string $paymentDate
    ): bool {
        if (!isset($this->payments[$policyId])) {
            $this->payments[$policyId] = [];
        }

        $this->payments[$policyId][] = [
            'loan_id' => $loanId,
            'policy_id' => $policyId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'recorded_at' => date('Y-m-d H:i:s'),
        ];

        return true;
    }

    /**
     * Get insurance payment history
     *
     * @param int $loanId
     * @param string $policyId
     * @return array Payment history
     */
    public function getInsurancePaymentHistory(int $loanId, string $policyId): array
    {
        return $this->payments[$policyId] ?? [];
    }

    /**
     * Get all active insurance policies for loan
     *
     * @param int $loanId
     * @return array Active policies
     */
    public function getActivePolicies(int $loanId): array
    {
        return array_filter(
            $this->policies,
            fn($p) => $p['loan_id'] === $loanId && $p['status'] === 'ACTIVE'
        );
    }

    /**
     * Get policy by ID
     *
     * @param string $policyId
     * @return array|null Policy or null if not found
     */
    public function getPolicy(string $policyId): ?array
    {
        return $this->policies[$policyId] ?? null;
    }

    /**
     * Calculate total annual insurance cost for loan
     *
     * @param int $loanId
     * @param Loan $loan
     * @return float Total annual insurance cost
     */
    public function calculateTotalAnnualInsuranceCost(int $loanId, Loan $loan): float
    {
        $policies = $this->getActivePolicies($loanId);
        $totalCost = 0.0;

        foreach ($policies as $policy) {
            $totalCost += $loan->getCurrentBalance() * $policy['annual_rate'];
        }

        return round($totalCost, 2);
    }

    /**
     * Get total insurance paid to date
     *
     * @param string $policyId
     * @return float Total paid
     */
    public function getTotalInsurancePaid(string $policyId): float
    {
        $payments = $this->payments[$policyId] ?? [];
        $total = 0.0;

        foreach ($payments as $payment) {
            $total += $payment['amount'];
        }

        return round($total, 2);
    }
}
