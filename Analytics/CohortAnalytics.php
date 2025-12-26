<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Analytics;

use Ksfraser\Amortizations\Persistence\Database;

/**
 * Cohort analysis for borrower groups
 */
class CohortAnalytics
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Group loans by origination cohort
     */
    public function getLoansByCohort(string $cohortPeriod = '%Y-%m'): array
    {
        $sql = "SELECT 
                    strftime(?, start_date) as cohort,
                    COUNT(*) as loan_count,
                    SUM(principal) as total_principal,
                    AVG(interest_rate) as avg_rate,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count
                FROM loans
                GROUP BY cohort
                ORDER BY cohort DESC";
        
        return $this->db->fetchAll($sql, [$cohortPeriod]);
    }

    /**
     * Calculate cohort retention/survival
     */
    public function getCohortSurvivalRate(string $cohort): float
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'active' THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(*), 0) as survival_rate
                FROM loans
                WHERE strftime('%Y-%m', start_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$cohort]);
        return (float)($result['survival_rate'] ?? 0);
    }

    /**
     * Group loans by borrower segment
     */
    public function getLoansByBorrowerSegment(): array
    {
        $sql = "SELECT 
                    CASE 
                        WHEN principal < 50000 THEN 'Small'
                        WHEN principal < 200000 THEN 'Medium'
                        ELSE 'Large'
                    END as segment,
                    COUNT(*) as loan_count,
                    SUM(principal) as total_principal,
                    AVG(interest_rate) as avg_rate
                FROM loans
                GROUP BY segment
                ORDER BY loan_count DESC";
        
        return $this->db->fetchAll($sql, []);
    }

    /**
     * Calculate default rate by cohort
     */
    public function getCohortDefaultRate(string $cohort): float
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'defaulted' THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(*), 0) as default_rate
                FROM loans
                WHERE strftime('%Y-%m', start_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$cohort]);
        return (float)($result['default_rate'] ?? 0);
    }
}
