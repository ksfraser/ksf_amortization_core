<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Analytics;

use Ksfraser\Amortizations\Persistence\Database;
use Ksfraser\Amortizations\Persistence\LoanRepository;
use Ksfraser\Amortizations\Persistence\PortfolioRepository;
use Ksfraser\Amortizations\Persistence\PaymentScheduleRepository;

/**
 * Loan aggregation and portfolio analytics
 */
class PortfolioAnalytics
{
    private Database $db;
    private LoanRepository $loanRepo;
    private PortfolioRepository $portfolioRepo;
    private PaymentScheduleRepository $scheduleRepo;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->loanRepo = new LoanRepository($db);
        $this->portfolioRepo = new PortfolioRepository($db);
        $this->scheduleRepo = new PaymentScheduleRepository($db);
    }

    /**
     * Calculate total principal balance across portfolio
     */
    public function getTotalPrincipalBalance(int $portfolioId): float
    {
        $sql = "SELECT SUM(ps.balance) as total FROM payment_schedules ps
                JOIN portfolio_loans pl ON ps.loan_id = pl.loan_id
                WHERE pl.portfolio_id = ? AND ps.status = 'pending'";
        
        $result = $this->db->fetchOne($sql, [$portfolioId]);
        return (float)($result['total'] ?? 0);
    }

    /**
     * Calculate weighted average interest rate
     */
    public function getWeightedAverageRate(int $portfolioId): float
    {
        $sql = "SELECT SUM(l.principal * l.interest_rate) / NULLIF(SUM(l.principal), 0) as avg_rate
                FROM loans l
                JOIN portfolio_loans pl ON l.id = pl.loan_id
                WHERE pl.portfolio_id = ? AND l.status = 'active'";
        
        $result = $this->db->fetchOne($sql, [$portfolioId]);
        return (float)($result['avg_rate'] ?? 0);
    }

    /**
     * Count loans by status in portfolio
     */
    public function getPortfolioLoanStatus(int $portfolioId): array
    {
        $sql = "SELECT l.status, COUNT(*) as count FROM loans l
                JOIN portfolio_loans pl ON l.id = pl.loan_id
                WHERE pl.portfolio_id = ?
                GROUP BY l.status";
        
        $results = $this->db->fetchAll($sql, [$portfolioId]);
        $status = [];
        foreach ($results as $row) {
            $status[$row['status']] = (int)$row['count'];
        }
        return $status;
    }

    /**
     * Get payment statistics for a month
     */
    public function getMonthlyPaymentStats(int $portfolioId, string $month): array
    {
        $sql = "SELECT 
                    COUNT(*) as payment_count,
                    SUM(ps.payment_amount) as total_payments,
                    SUM(ps.principal) as principal_paid,
                    SUM(ps.interest) as interest_paid
                FROM payment_schedules ps
                JOIN portfolio_loans pl ON ps.loan_id = pl.loan_id
                WHERE pl.portfolio_id = ? 
                AND strftime('%Y-%m', ps.due_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$portfolioId, $month]);
        return [
            'payment_count' => (int)($result['payment_count'] ?? 0),
            'total_payments' => (float)($result['total_payments'] ?? 0),
            'principal_paid' => (float)($result['principal_paid'] ?? 0),
            'interest_paid' => (float)($result['interest_paid'] ?? 0),
        ];
    }
}
