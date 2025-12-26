<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Compliance;

use Ksfraser\Amortizations\Persistence\Database;
use Ksfraser\Amortizations\Persistence\LoanRepository;

/**
 * TILA (Truth in Lending Act) compliance and disclosures
 */
class TILACompliance
{
    private Database $db;
    private LoanRepository $loanRepo;
    private APRValidator $aprValidator;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->loanRepo = new LoanRepository($db);
        $this->aprValidator = new APRValidator($db);
    }

    /**
     * Generate TILA disclosure statement
     */
    public function generateDisclosure(int $loanId): array
    {
        $loan = $this->loanRepo->find($loanId);
        if (!$loan) {
            return [];
        }

        return [
            'loan_id' => $loanId,
            'creditor' => 'KSF Loan Services',
            'date_generated' => date('Y-m-d'),
            
            // Required disclosures
            'amount_financed' => $this->aprValidator->getLoanAmountFinanced($loanId),
            'finance_charge' => $this->aprValidator->calculateFinanceCharge($loanId),
            'total_of_payments' => $this->aprValidator->getTotalPaymentObligation($loanId),
            'annual_percentage_rate' => $this->aprValidator->calculateAPR($loanId),
            
            // Additional required info
            'loan_amount' => (float)$loan['principal'],
            'interest_rate' => (float)$loan['interest_rate'],
            'loan_term_months' => (int)$loan['term_months'],
            'payment_schedule' => 'Monthly',
            'first_payment_due' => $this->getFirstPaymentDate($loanId),
            'final_payment_due' => $this->getFinalPaymentDate($loanId),
            'regular_payment_amount' => $this->getRegularPaymentAmount($loanId),
            
            // Risk disclosures
            'prepayment_penalty' => 'None',
            'late_payment_fee' => $this->getLatePaymentFee(),
            'variable_rate_notice' => 'Rate is fixed',
        ];
    }

    /**
     * Get first payment due date
     */
    public function getFirstPaymentDate(int $loanId): ?string
    {
        $sql = "SELECT due_date FROM payment_schedules 
                WHERE loan_id = ? ORDER BY payment_number ASC LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return $result ? $result['due_date'] : null;
    }

    /**
     * Get final payment due date
     */
    public function getFinalPaymentDate(int $loanId): ?string
    {
        $sql = "SELECT due_date FROM payment_schedules 
                WHERE loan_id = ? ORDER BY payment_number DESC LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return $result ? $result['due_date'] : null;
    }

    /**
     * Get regular payment amount
     */
    public function getRegularPaymentAmount(int $loanId): float
    {
        $sql = "SELECT payment_amount FROM payment_schedules 
                WHERE loan_id = ? ORDER BY payment_number ASC LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$loanId]);
        return $result ? (float)$result['payment_amount'] : 0;
    }

    /**
     * Get late payment fee (configurable)
     */
    public function getLatePaymentFee(): float
    {
        return 25.00; // Default $25 late fee
    }

    /**
     * Validate TILA compliance
     */
    public function validateCompliance(int $loanId): array
    {
        $disclosure = $this->generateDisclosure($loanId);
        $violations = [];

        // Check for required fields
        $requiredFields = [
            'amount_financed', 'finance_charge', 'total_of_payments',
            'annual_percentage_rate', 'loan_amount', 'interest_rate'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($disclosure[$field])) {
                $violations[] = "Missing required disclosure: $field";
            }
        }

        return $violations;
    }
}
