<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Analytics;

use Ksfraser\Amortizations\Persistence\Database;

/**
 * Time series analysis for loan performance
 */
class TimeSeriesAnalytics
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get payment history for a loan over time
     */
    public function getLoanPaymentHistory(int $loanId): array
    {
        $sql = "SELECT 
                    due_date,
                    payment_amount,
                    principal,
                    interest,
                    balance,
                    status
                FROM payment_schedules
                WHERE loan_id = ?
                ORDER BY payment_number ASC";
        
        return $this->db->fetchAll($sql, [$loanId]);
    }

    /**
     * Get cumulative interest paid over time
     */
    public function getCumulativeInterestPaid(int $loanId): array
    {
        $sql = "SELECT 
                    due_date,
                    SUM(interest) OVER (ORDER BY payment_number) as cumulative_interest,
                    SUM(principal) OVER (ORDER BY payment_number) as cumulative_principal
                FROM payment_schedules
                WHERE loan_id = ?
                ORDER BY payment_number ASC";
        
        return $this->db->fetchAll($sql, [$loanId]);
    }

    /**
     * Calculate amortization rate (balance reduction per period)
     */
    public function getAmortizationRate(int $loanId): float
    {
        $sql = "SELECT 
                    (MAX(balance) - MIN(balance)) / NULLIF(COUNT(*), 0) as avg_reduction
                FROM payment_schedules
                WHERE loan_id = ?";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return (float)($result['avg_reduction'] ?? 0);
    }

    /**
     * Get payment frequency distribution
     */
    public function getPaymentFrequency(int $loanId, string $period): int
    {
        $sql = "SELECT COUNT(*) as count FROM payment_schedules
                WHERE loan_id = ? AND strftime(?, due_date) IS NOT NULL";
        
        $result = $this->db->fetchOne($sql, [$loanId, $period]);
        return (int)($result['count'] ?? 0);
    }
}
