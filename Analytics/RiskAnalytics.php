<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Analytics;

use Ksfraser\Amortizations\Persistence\Database;

/**
 * Risk metrics and scoring
 */
class RiskAnalytics
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Calculate portfolio concentration risk
     */
    public function getConcentrationRisk(int $portfolioId): float
    {
        $sql = "SELECT 
                    SUM(POWER((principal / NULLIF(total_principal, 0)), 2)) as hhi
                FROM (
                    SELECT 
                        l.principal,
                        (SELECT SUM(principal) FROM loans l2 
                         JOIN portfolio_loans pl2 ON l2.id = pl2.loan_id 
                         WHERE pl2.portfolio_id = ?) as total_principal
                    FROM loans l
                    JOIN portfolio_loans pl ON l.id = pl.loan_id
                    WHERE pl.portfolio_id = ?
                )";
        
        $result = $this->db->fetchOne($sql, [$portfolioId, $portfolioId]);
        return (float)($result['hhi'] ?? 0);
    }

    /**
     * Calculate duration weighted by balance
     */
    public function getWeightedDuration(int $portfolioId): float
    {
        $sql = "SELECT 
                    SUM(ps.balance * ps.payment_number) / NULLIF(SUM(ps.balance), 0) as duration
                FROM payment_schedules ps
                JOIN portfolio_loans pl ON ps.loan_id = pl.loan_id
                WHERE pl.portfolio_id = ? AND ps.status = 'pending'";
        
        $result = $this->db->fetchOne($sql, [$portfolioId]);
        return (float)($result['duration'] ?? 0);
    }

    /**
     * Calculate portfolio yield
     */
    public function getPortfolioYield(int $portfolioId): float
    {
        $sql = "SELECT 
                    SUM(ps.interest) * 100.0 / NULLIF(AVG(ps.balance), 0) as yield
                FROM payment_schedules ps
                JOIN portfolio_loans pl ON ps.loan_id = pl.loan_id
                WHERE pl.portfolio_id = ? AND ps.status = 'pending'";
        
        $result = $this->db->fetchOne($sql, [$portfolioId]);
        return (float)($result['yield'] ?? 0);
    }

    /**
     * Calculate loss severity indicator
     */
    public function getLossSeverity(int $portfolioId): float
    {
        $sql = "SELECT 
                    SUM(CASE WHEN l.status = 'defaulted' THEN l.principal ELSE 0 END) * 100.0 /
                    NULLIF(SUM(l.principal), 0) as loss_severity
                FROM loans l
                JOIN portfolio_loans pl ON l.id = pl.loan_id
                WHERE pl.portfolio_id = ?";
        
        $result = $this->db->fetchOne($sql, [$portfolioId]);
        return (float)($result['loss_severity'] ?? 0);
    }
}
