<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Compliance;

use Ksfraser\Amortizations\Persistence\Database;
use Ksfraser\Amortizations\Persistence\LoanRepository;

/**
 * APR calculation and validation per Regulation Z
 */
class APRValidator
{
    private Database $db;
    private LoanRepository $loanRepo;
    private const APR_TOLERANCE = 0.005; // 0.5% tolerance
    private const DECIMALS = 3;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->loanRepo = new LoanRepository($db);
    }

    /**
     * Calculate APR for a loan
     * APR = (Periodic Rate × 365) / Days in Period
     */
    public function calculateAPR(int $loanId): float
    {
        $loan = $this->loanRepo->find($loanId);
        if (!$loan) {
            return 0;
        }

        // Simple APR calculation based on interest rate
        // In production, would account for all fees and charges
        $principalAmount = (float)$loan['principal'];
        $periodicRate = (float)$loan['interest_rate'] / 12; // Monthly rate
        
        // APR for monthly payments
        $apr = $periodicRate * 12;
        
        return round($apr, self::DECIMALS);
    }

    /**
     * Validate APR disclosure matches calculated APR
     */
    public function validateAPRDisclosure(int $loanId, float $disclosedAPR): bool
    {
        $calculatedAPR = $this->calculateAPR($loanId);
        $difference = abs($calculatedAPR - $disclosedAPR);
        
        return $difference <= self::APR_TOLERANCE;
    }

    /**
     * Calculate finance charge
     */
    public function calculateFinanceCharge(int $loanId): float
    {
        $sql = "SELECT SUM(interest) as total_interest FROM payment_schedules
                WHERE loan_id = ?";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return (float)($result['total_interest'] ?? 0);
    }

    /**
     * Get loan amount financed
     */
    public function getLoanAmountFinanced(int $loanId): float
    {
        $loan = $this->loanRepo->find($loanId);
        return $loan ? (float)$loan['principal'] : 0;
    }

    /**
     * Calculate total payment obligation
     */
    public function getTotalPaymentObligation(int $loanId): float
    {
        $sql = "SELECT SUM(payment_amount) as total FROM payment_schedules
                WHERE loan_id = ?";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return (float)($result['total'] ?? 0);
    }
}
