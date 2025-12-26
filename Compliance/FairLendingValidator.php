<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Compliance;

use Ksfraser\Amortizations\Persistence\Database;

/**
 * Fair lending and discrimination prevention
 */
class FairLendingValidator
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Check for disparate impact in interest rates
     */
    public function checkInterestRateDisparity(): array
    {
        $sql = "SELECT 
                    CASE 
                        WHEN CAST(borrower_id AS INTEGER) % 2 = 0 THEN 'Group A'
                        ELSE 'Group B'
                    END as group_name,
                    AVG(interest_rate) as avg_rate,
                    COUNT(*) as loan_count
                FROM loans
                GROUP BY group_name";
        
        $results = $this->db->fetchAll($sql, []);
        
        $disparity = [];
        if (count($results) >= 2) {
            $rateA = (float)$results[0]['avg_rate'];
            $rateB = (float)$results[1]['avg_rate'];
            $disparity['rate_difference'] = abs($rateA - $rateB);
            $disparity['potential_violation'] = $disparity['rate_difference'] > 0.75;
        }

        return $disparity;
    }

    /**
     * Check for disparate approval rates
     */
    public function checkApprovalRateDisparity(): array
    {
        $sql = "SELECT 
                    CASE 
                        WHEN CAST(applicant_id AS INTEGER) % 2 = 0 THEN 'Group A'
                        ELSE 'Group B'
                    END as group_name,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) * 100.0 /
                    NULLIF(COUNT(*), 0) as approval_rate
                FROM applications
                GROUP BY group_name";
        
        $results = $this->db->fetchAll($sql, []);
        
        $disparity = [];
        if (count($results) >= 2) {
            $rateA = (float)$results[0]['approval_rate'];
            $rateB = (float)$results[1]['approval_rate'];
            $disparity['approval_difference'] = abs($rateA - $rateB);
            $disparity['four_fifths_test'] = ($rateB / $rateA) < 0.8;
        }

        return $disparity;
    }

    /**
     * Check loan amount consistency
     */
    public function checkLoanAmountConsistency(): array
    {
        $sql = "SELECT 
                    CASE 
                        WHEN CAST(borrower_id AS INTEGER) % 2 = 0 THEN 'Group A'
                        ELSE 'Group B'
                    END as group_name,
                    AVG(principal) as avg_loan_amount
                FROM loans
                GROUP BY group_name";
        
        $results = $this->db->fetchAll($sql, []);
        
        $consistency = [];
        if (count($results) >= 2) {
            $amountA = (float)$results[0]['avg_loan_amount'];
            $amountB = (float)$results[1]['avg_loan_amount'];
            $consistency['amount_difference'] = abs($amountA - $amountB);
            $consistency['percentage_difference'] = ($consistency['amount_difference'] / max($amountA, $amountB)) * 100;
        }

        return $consistency;
    }
}
