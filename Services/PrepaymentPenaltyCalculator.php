<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Utils\DecimalCalculator;

/**
 * PrepaymentPenaltyCalculator - Prepayment Penalty Management Service
 *
 * Handles calculation and tracking of prepayment penalties for early loan
 * repayment scenarios. Supports multiple penalty strategies:
 *
 * 1. Percentage-based: Applied as percentage of prepayment amount
 * 2. Fixed-amount: Static penalty regardless of prepayment size
 * 3. Declining-scale: Graduated percentage based on months remaining
 *
 * Features:
 * - Configure penalty type and amounts
 * - Calculate penalties with optional caps and windows
 * - Track penalty history for revenue reporting
 * - Support penalty waivers with authorization tracking
 * - Integration with revenue posting systems
 *
 * @package Ksfraser\Amortizations\Services
 */
class PrepaymentPenaltyCalculator
{
    /**
     * Penalty configurations by loan ID
     * @var array
     */
    private $penalties = [];

    /**
     * Penalty history tracking
     * @var array
     */
    private $penaltyHistory = [];

    /**
     * Decimal calculator for precision
     * @var DecimalCalculator
     */
    private $calculator;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Configure prepayment penalty for a loan
     *
     * @param int $loanId Loan identifier
     * @param string $type Penalty type: 'percentage', 'fixed', 'declining'
     * @param float|null $amountOrPercent Amount or percentage value
     * @param array|null $schedule Declining scale schedule
     * @param float|null $maximumCap Maximum penalty amount (optional)
     * @param array|null $window Penalty window ['start_month' => int, 'end_month' => int]
     * @return array Penalty configuration
     */
    public function setPenalty(
        $loanId,
        $type,
        $amountOrPercent = null,
        $schedule = null,
        $maximumCap = null,
        $window = null
    ) {
        $penalty = [
            'loan_id' => $loanId,
            'type' => $type,
            'amount_or_percent' => $amountOrPercent,
            'schedule' => $schedule,
            'maximum_cap' => $maximumCap,
            'window' => $window,
            'status' => 'ACTIVE',
            'waived_at' => null,
            'waived_by' => null,
            'waive_reason' => null,
        ];

        $this->penalties[$loanId] = $penalty;
        return $penalty;
    }

    /**
     * Calculate prepayment penalty for given amount
     *
     * @param int $loanId Loan identifier
     * @param float $prepaymentAmount Amount being prepaid
     * @param mixed $loanState Optional loan state for validation
     * @return float Calculated penalty amount
     */
    public function calculatePenalty($loanId, $prepaymentAmount, $loanState = null)
    {
        // No penalty if not configured
        if (!isset($this->penalties[$loanId])) {
            return 0.00;
        }

        $penalty = $this->penalties[$loanId];

        // No penalty if waived
        if ($penalty['status'] === 'WAIVED') {
            return 0.00;
        }

        // Check penalty window if defined
        if ($penalty['window'] && $loanState) {
            if (!$this->isInPenaltyWindow($penalty, $loanState)) {
                return 0.00;
            }
        }

        // Calculate based on penalty type
        $penaltyAmount = match ($penalty['type']) {
            'percentage' => $this->calculatePercentagePenalty($prepaymentAmount, $penalty),
            'fixed' => $penalty['amount_or_percent'],
            'declining' => $this->calculateDecliningPenalty($prepaymentAmount, $penalty, $loanState),
            default => 0.00,
        };

        // Apply maximum cap if defined
        if ($penalty['maximum_cap'] !== null && $penaltyAmount > $penalty['maximum_cap']) {
            $penaltyAmount = $penalty['maximum_cap'];
        }

        return round($penaltyAmount, 2);
    }

    /**
     * Calculate percentage-based penalty
     *
     * @param float $prepaymentAmount Amount being prepaid
     * @param array $penaltyConfig Penalty configuration
     * @return float Penalty amount
     */
    private function calculatePercentagePenalty($prepaymentAmount, $penaltyConfig)
    {
        $percent = $penaltyConfig['amount_or_percent'];
        $penaltyAmount = $this->calculator->multiply(
            $prepaymentAmount,
            $this->calculator->divide($percent, 100)
        );

        return (float)$penaltyAmount;
    }

    /**
     * Calculate declining-scale penalty based on months remaining
     *
     * @param float $prepaymentAmount Amount being prepaid
     * @param array $penaltyConfig Penalty configuration
     * @param object|null $loanState Loan state with months remaining
     * @return float Penalty amount
     */
    private function calculateDecliningPenalty($prepaymentAmount, $penaltyConfig, $loanState = null)
    {
        // Default to 0 if no schedule or loan state
        if (!$penaltyConfig['schedule'] || !$loanState) {
            return 0.00;
        }

        $schedule = $penaltyConfig['schedule'];
        $monthsRemaining = $loanState->getMonths() ?? 0;

        // Find applicable rate from schedule (first matching threshold)
        $applicablePercent = 0.00;
        foreach ($schedule as $tier) {
            if ($monthsRemaining >= $tier['min_months']) {
                $applicablePercent = $tier['percent'];
                break;
            }
        }

        // Calculate penalty with applicable percentage
        $penaltyAmount = $this->calculator->multiply(
            $prepaymentAmount,
            $this->calculator->divide($applicablePercent, 100)
        );

        return (float)$penaltyAmount;
    }

    /**
     * Check if prepayment is within penalty window
     *
     * @param array $penaltyConfig Penalty configuration
     * @param mixed $loanState Loan state object
     * @return bool True if within window
     */
    private function isInPenaltyWindow($penaltyConfig, $loanState)
    {
        if (!$loanState) {
            return false;
        }

        $window = $penaltyConfig['window'];
        $monthsRemaining = $loanState->getMonths() ?? 0;
        
        // Get original term - should be in window config
        $originalTerm = $window['original_months'] ?? 60;
        
        $monthsElapsed = $originalTerm - $monthsRemaining;

        // Penalty window defined in months elapsed from start
        // e.g., window ['start_month' => 1, 'end_month' => 24] means
        // penalty applies during months 1-24 from the start
        $inWindow = $monthsElapsed >= $window['start_month'] &&
                    $monthsElapsed <= $window['end_month'];

        return $inWindow;
    }

    /**
     * Waive prepayment penalty for a loan
     *
     * @param int $loanId Loan identifier
     * @param string $authorizedBy User authorizing the waiver
     * @param string $reason Reason for waiver
     * @return bool Success
     */
    public function waivePenalty($loanId, $authorizedBy, $reason)
    {
        if (!isset($this->penalties[$loanId])) {
            return false;
        }

        $this->penalties[$loanId]['status'] = 'WAIVED';
        $this->penalties[$loanId]['waived_at'] = new \DateTimeImmutable();
        $this->penalties[$loanId]['waived_by'] = $authorizedBy;
        $this->penalties[$loanId]['waive_reason'] = $reason;

        return true;
    }

    /**
     * Record a penalty charge for reporting
     *
     * @param int $loanId Loan identifier
     * @param float $amount Penalty amount charged
     * @param string $reason Reason for penalty (e.g., 'extra_payment', 'early_payoff')
     * @return void
     */
    public function recordPenaltyCharge($loanId, $amount, $reason)
    {
        if (!isset($this->penaltyHistory[$loanId])) {
            $this->penaltyHistory[$loanId] = [];
        }

        $this->penaltyHistory[$loanId][] = [
            'amount' => round($amount, 2),
            'reason' => $reason,
            'recorded_at' => new \DateTimeImmutable(),
        ];
    }

    /**
     * Get penalty charge history for a loan
     *
     * @param int $loanId Loan identifier
     * @return array Penalty history records
     */
    public function getPenaltyHistory($loanId)
    {
        return $this->penaltyHistory[$loanId] ?? [];
    }

    /**
     * Get total penalties collected for a loan
     *
     * @param int $loanId Loan identifier
     * @return float Total penalty amount
     */
    public function getTotalPenaltiesCollected($loanId)
    {
        $history = $this->getPenaltyHistory($loanId);

        $total = 0.00;
        foreach ($history as $record) {
            $total = $this->calculator->add($total, $record['amount']);
        }

        return (float)$total;
    }

    /**
     * Get penalty configuration for a loan
     *
     * @param int $loanId Loan identifier
     * @return array|null Penalty configuration or null if not set
     */
    public function getPenalty($loanId)
    {
        return $this->penalties[$loanId] ?? null;
    }
}
