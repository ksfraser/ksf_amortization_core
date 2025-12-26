<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Analytics;

use Ksfraser\Amortizations\Persistence\Database;

/**
 * Predictive analytics for loan performance
 */
class PredictiveAnalytics
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Predict remaining term for loan
     */
    public function predictRemainingTerm(int $loanId): int
    {
        $sql = "SELECT COUNT(*) as remaining FROM payment_schedules
                WHERE loan_id = ? AND status IN ('pending', 'scheduled')";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return (int)($result['remaining'] ?? 0);
    }

    /**
     * Estimate total interest over loan life
     */
    public function estimateTotalInterest(int $loanId): float
    {
        $sql = "SELECT SUM(interest) as total FROM payment_schedules
                WHERE loan_id = ?";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return (float)($result['total'] ?? 0);
    }

    /**
     * Calculate loan-to-value ratio estimate
     */
    public function estimateLTV(int $loanId): float
    {
        $sql = "SELECT 
                    (SELECT balance FROM payment_schedules 
                     WHERE loan_id = ? ORDER BY payment_number DESC LIMIT 1) /
                    NULLIF((SELECT principal FROM loans WHERE id = ?), 0) as ltv";
        
        $result = $this->db->fetchOne($sql, [$loanId, $loanId]);
        return (float)($result['ltv'] ?? 0);
    }

    /**
     * Predict delinquency risk (simple model)
     */
    public function predictDelinquencyRisk(int $loanId): float
    {
        // Simple heuristic: check missed/late payments
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'late' THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(*), 0) as risk_score
                FROM payment_schedules
                WHERE loan_id = ?";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return (float)($result['risk_score'] ?? 0);
    }

    /**
     * Get prepayment probability based on payment history
     */
    public function estimatePrepaymentProbability(int $loanId): float
    {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM payment_schedules 
                     WHERE loan_id = ? AND paid_date IS NOT NULL) * 100.0 /
                    NULLIF((SELECT COUNT(*) FROM payment_schedules 
                     WHERE loan_id = ?), 0) as prepay_rate";
        
        $result = $this->db->fetchOne($sql, [$loanId, $loanId]);
        return (float)($result['prepay_rate'] ?? 0);
    }
}
