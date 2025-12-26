<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Compliance;

use Ksfraser\Amortizations\Persistence\Database;
use Ksfraser\Amortizations\Persistence\AuditLogRepository;

/**
 * Regulatory reporting and compliance documentation
 */
class RegulatoryReporting
{
    private Database $db;
    private AuditLogRepository $auditRepo;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->auditRepo = new AuditLogRepository($db);
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(string $periodStart, string $periodEnd): array
    {
        return [
            'report_date' => date('Y-m-d H:i:s'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'reporting_entity' => 'KSF Loan Services',
            'loan_metrics' => $this->getLoanMetrics($periodStart, $periodEnd),
            'payment_metrics' => $this->getPaymentMetrics($periodStart, $periodEnd),
            'compliance_metrics' => $this->getComplianceMetrics(),
        ];
    }

    /**
     * Get loan metrics for period
     */
    public function getLoanMetrics(string $periodStart, string $periodEnd): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_loans,
                    SUM(principal) as total_principal,
                    AVG(principal) as avg_principal,
                    AVG(interest_rate) as avg_rate,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count
                FROM loans
                WHERE start_date >= ? AND start_date <= ?";
        
        $result = $this->db->fetchOne($sql, [$periodStart, $periodEnd]);
        return [
            'total_loans_originated' => (int)($result['total_loans'] ?? 0),
            'total_principal' => (float)($result['total_principal'] ?? 0),
            'average_loan_size' => (float)($result['avg_principal'] ?? 0),
            'average_interest_rate' => (float)($result['avg_rate'] ?? 0),
            'active_loans' => (int)($result['active_count'] ?? 0),
        ];
    }

    /**
     * Get payment metrics for period
     */
    public function getPaymentMetrics(string $periodStart, string $periodEnd): array
    {
        $sql = "SELECT 
                    COUNT(*) as payments_processed,
                    SUM(payment_amount) as total_payments,
                    SUM(principal) as principal_collected,
                    SUM(interest) as interest_collected,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_payments,
                    COUNT(CASE WHEN status = 'missed' THEN 1 END) as missed_payments
                FROM payment_schedules
                WHERE due_date >= ? AND due_date <= ?";
        
        $result = $this->db->fetchOne($sql, [$periodStart, $periodEnd]);
        return [
            'payments_processed' => (int)($result['payments_processed'] ?? 0),
            'total_collections' => (float)($result['total_payments'] ?? 0),
            'principal_collected' => (float)($result['principal_collected'] ?? 0),
            'interest_collected' => (float)($result['interest_collected'] ?? 0),
            'late_payments_count' => (int)($result['late_payments'] ?? 0),
            'missed_payments_count' => (int)($result['missed_payments'] ?? 0),
            'delinquency_rate' => $this->calculateDelinquencyRate($periodStart, $periodEnd),
        ];
    }

    /**
     * Calculate delinquency rate
     */
    public function calculateDelinquencyRate(string $periodStart, string $periodEnd): float
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN status IN ('late', 'missed') THEN 1 END) * 100.0 /
                    NULLIF(COUNT(*), 0) as delinquency_rate
                FROM payment_schedules
                WHERE due_date >= ? AND due_date <= ?";
        
        $result = $this->db->fetchOne($sql, [$periodStart, $periodEnd]);
        return (float)($result['delinquency_rate'] ?? 0);
    }

    /**
     * Get compliance metrics
     */
    public function getComplianceMetrics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_events
                FROM audit_logs
                WHERE action IN ('disclosure_violation', 'fair_lending_check', 'compliance_review')";
        
        $result = $this->db->fetchOne($sql, []);
        
        return [
            'total_compliance_events' => (int)($result['total_events'] ?? 0),
            'violations_reported' => 0,
            'last_audit_date' => date('Y-m-d'),
        ];
    }

    /**
     * Generate audit trail for loan
     */
    public function getAuditTrail(int $loanId): array
    {
        return $this->auditRepo->getHistory('Loan', $loanId);
    }
}
