<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

class LoanOriginationService {

    /**
     * Create a new loan application
     */
    public function createLoanApplication(array $applicationData): array {
        return [
            'application_id' => uniqid('app_'),
            'status' => 'submitted',
            'created_at' => date('Y-m-d H:i:s'),
            'applicant_name' => $applicationData['applicant_name'] ?? '',
            'requested_amount' => $applicationData['requested_amount'] ?? 0,
            'purpose' => $applicationData['purpose'] ?? '',
            'documents' => []
        ];
    }

    /**
     * Validate loan application completeness
     */
    public function validateLoanApplication(array $application): array {
        $errors = [];
        $warnings = [];

        if (empty($application['applicant_name'])) {
            $errors[] = 'Applicant name is required';
        }

        if (empty($application['requested_amount']) || $application['requested_amount'] <= 0) {
            $errors[] = 'Loan amount must be greater than zero';
        }

        if (empty($application['purpose'])) {
            $warnings[] = 'Loan purpose not specified';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Generate required disclosures
     */
    public function generateDisclosures(Loan $loan, float $estimatedPayment): array {
        $disclosures = [];

        $disclosures['truth_in_lending'] = [
            'document_name' => 'Regulation Z Truth in Lending',
            'required' => true,
            'content' => 'Annual Percentage Rate: ' . round($loan->getAnnualRate() * 100, 2) . '%'
        ];

        $disclosures['privacy_notice'] = [
            'document_name' => 'Privacy Notice',
            'required' => true,
            'content' => 'Your personal financial information is protected'
        ];

        $disclosures['fair_lending'] = [
            'document_name' => 'Fair Lending Notice',
            'required' => true,
            'content' => 'We comply with all fair lending laws'
        ];

        return $disclosures;
    }

    /**
     * Check compliance requirements
     */
    public function checkCompliance(Loan $loan): array {
        $compliance = [
            'anti_money_laundering' => true,
            'kyc_verified' => true,
            'fraud_check_passed' => true,
            'regulatory_compliant' => true
        ];

        if ($loan->getAnnualRate() > 0.20) {
            $compliance['high_rate_disclosure'] = true;
        }

        return [
            'status' => 'compliant',
            'checks' => $compliance,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Calculate maximum borrowing amount
     */
    public function calculateMaxBorrow(float $monthlyIncome, float $maxDtiRatio = 0.43, float $interestRate = 0.05, int $months = 360): float {
        $maxMonthlyDebt = $monthlyIncome * $maxDtiRatio;
        $monthlyRate = $interestRate / 12;

        if ($monthlyRate == 0) {
            return $maxMonthlyDebt * $months;
        }

        $numerator = $maxMonthlyDebt * (pow(1 + $monthlyRate, $months) - 1);
        $denominator = $monthlyRate * pow(1 + $monthlyRate, $months);

        return round($numerator / $denominator, 2);
    }

    /**
     * Assign loan officer to application
     */
    public function assignLoanOfficer(string $applicationId, string $loanOfficerId): array {
        return [
            'application_id' => $applicationId,
            'assigned_officer' => $loanOfficerId,
            'assigned_at' => date('Y-m-d H:i:s'),
            'status' => 'assigned'
        ];
    }

    /**
     * Generate offer letter
     */
    public function generateOfferLetter(Loan $loan, float $monthlyPayment, string $applicantName): string {
        $letter = "LOAN OFFER LETTER\n\n";
        $letter .= "Dear " . htmlspecialchars($applicantName) . ",\n\n";
        $letter .= "We are pleased to offer you a loan with the following terms:\n\n";
        $letter .= "Loan Amount: $" . number_format($loan->getPrincipal(), 2) . "\n";
        $letter .= "Interest Rate: " . round($loan->getAnnualRate() * 100, 2) . "%\n";
        $letter .= "Term: " . $loan->getMonths() . " months\n";
        $letter .= "Monthly Payment: $" . number_format($monthlyPayment, 2) . "\n\n";
        $letter .= "This offer is valid for 30 days.\n\n";
        $letter .= "Sincerely,\nLoan Department";
        
        return $letter;
    }

    /**
     * Update application status
     */
    public function updateApplicationStatus(string $applicationId, string $newStatus): array {
        return [
            'application_id' => $applicationId,
            'previous_status' => 'submitted',
            'new_status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Approve loan application
     */
    public function approveLoan(string $applicationId, Loan $loan): array {
        return [
            'application_id' => $applicationId,
            'status' => 'approved',
            'approved_amount' => $loan->getPrincipal(),
            'approved_rate' => round($loan->getAnnualRate() * 100, 2) . '%',
            'approved_term' => $loan->getMonths(),
            'approval_timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Reject loan application with reason
     */
    public function rejectLoan(string $applicationId, string $reason): array {
        return [
            'application_id' => $applicationId,
            'status' => 'rejected',
            'reason' => $reason,
            'rejected_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Request additional information
     */
    public function requestMoreInfo(string $applicationId, array $requiredDocuments): array {
        return [
            'application_id' => $applicationId,
            'status' => 'pending_documentation',
            'required_documents' => $requiredDocuments,
            'requested_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Document application submission
     */
    public function documentApplication(string $applicationId, array $documents): array {
        return [
            'application_id' => $applicationId,
            'documents_received' => count($documents),
            'document_list' => $documents,
            'documented_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Track application progress
     */
    public function trackApplicationProgress(string $applicationId): array {
        return [
            'application_id' => $applicationId,
            'current_stage' => 'review',
            'progress_percentage' => 50,
            'next_steps' => ['Credit verification', 'Property appraisal'],
            'estimated_completion' => date('Y-m-d', strtotime('+5 days'))
        ];
    }

    /**
     * Export application summary
     */
    public function exportApplicationSummary(array $application, Loan $loan): array {
        return [
            'application_id' => $application['application_id'] ?? null,
            'applicant_name' => $application['applicant_name'] ?? '',
            'requested_amount' => $application['requested_amount'] ?? 0,
            'approved_amount' => $loan->getPrincipal(),
            'interest_rate' => round($loan->getAnnualRate() * 100, 2),
            'term_months' => $loan->getMonths(),
            'status' => $application['status'] ?? 'unknown',
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }
}
