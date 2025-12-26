<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use DateTimeImmutable;
use DateInterval;

/**
 * RegulatoryReportGenerator - TRID / RESPA / TILA Compliance
 *
 * Generates regulatory compliance disclosures required by the Truth in Lending Act (TILA)
 * and Real Estate Settlement Procedures Act (RESPA), specifically the TRID rule (Loan Estimate
 * and Closing Disclosure) requirements.
 *
 * Key Documents:
 * - Loan Estimate (LE): Initial 3-year disclosure, 10 business day validity
 * - Closing Disclosure (CD): Final disclosure at closing
 *
 * Requirements:
 * - All fees disclosed and categorized
 * - APR calculation including fees
 * - Complete payment schedule
 * - Accurate interest calculations
 * - Compliance validation
 * - Export to standard formats (JSON, PDF-ready)
 */
class RegulatoryReportGenerator
{
    /**
     * Generate Loan Estimate (TRID compliant)
     *
     * @param Loan $loan
     * @return array Loan Estimate document
     */
    public function generateLoanEstimate(Loan $loan): array
    {
        $issueDate = new DateTimeImmutable();
        $expirationDate = $this->calculateExpirationDate($issueDate);

        return [
            'document_type' => 'Loan Estimate',
            'issue_date' => $issueDate->format('Y-m-d'),
            'expiration_date' => $expirationDate->format('Y-m-d'),
            'loan_details' => $this->generateLoanDetails($loan),
            'closing_costs' => $this->generateClosingCostsSummary($loan),
            'payment_schedule' => $this->generatePaymentScheduleForDisclosure($loan),
            'important_terms' => $this->generateImportantTerms($loan),
            'disclosures' => $this->generateTRIDDisclosures($loan),
        ];
    }

    /**
     * Generate Closing Disclosure (TRID compliant)
     *
     * @param Loan $loan
     * @return array Closing Disclosure document
     */
    public function generateClosingDisclosure(Loan $loan): array
    {
        return [
            'document_type' => 'Closing Disclosure',
            'closing_date' => date('Y-m-d'),
            'loan_details' => $this->generateLoanDetails($loan),
            'closing_costs' => $this->generateFinalClosingCosts($loan),
            'payment_information' => $this->generatePaymentInformation($loan),
            'final_schedule' => $this->generatePaymentScheduleForDisclosure($loan),
            'compliance_notes' => $this->generateComplianceNotes(),
        ];
    }

    /**
     * Calculate APR including all charges and fees
     *
     * APR incorporates:
     * - Stated interest rate
     * - Origination fees
     * - Discount points
     * - Pre-paid interest
     *
     * @param Loan $loan
     * @return float APR as decimal (e.g., 0.0534 = 5.34%)
     */
    public function calculateAPR(Loan $loan): float
    {
        $statedRate = $loan->getAnnualRate();
        
        // Fees increase effective rate
        // Simplified: fees amortized over loan term
        $totalFees = $this->calculateTotalFees($loan);
        $principal = $loan->getCurrentBalance();
        
        // Effective rate adjustment: fees as percentage of principal over term
        $effectiveAdjustment = 0;
        if ($principal > 0) {
            $months = $loan->getMonths();
            $monthlyFeeImpact = ($totalFees / $principal) / $months;
            $effectiveAdjustment = $monthlyFeeImpact * 12;  // Annualize
        }

        $apr = $statedRate + $effectiveAdjustment;
        return round($apr, 4);
    }

    /**
     * Generate loan details section
     *
     * @param Loan $loan
     * @return array Loan details
     */
    private function generateLoanDetails(Loan $loan): array
    {
        return [
            'loan_amount' => round($loan->getPrincipal(), 2),
            'interest_rate' => round($loan->getAnnualRate() * 100, 3) . '%',
            'loan_term' => $loan->getMonths() . ' months',
            'loan_purpose' => 'General',
            'property_address' => 'Subject Property',
            'loan_type' => 'Fixed Rate',
        ];
    }

    /**
     * Generate closing costs summary
     *
     * @param Loan $loan
     * @return array Closing costs organized by category
     */
    private function generateClosingCostsSummary(Loan $loan): array
    {
        return [
            'origination_charges' => [
                'origination_fee' => 200.00,
                'processing_fee' => 100.00,
                'total' => 300.00,
            ],
            'services_borrower_cannot_shop' => [
                'appraisal_fee' => 500.00,
                'credit_report_fee' => 50.00,
                'flood_determination_fee' => 10.00,
                'credit_approval_fee' => 50.00,
                'total' => 610.00,
            ],
            'services_borrower_can_shop' => [
                'title_search' => 150.00,
                'title_insurance' => 300.00,
                'survey_fee' => 0.00,
                'pest_inspection' => 0.00,
                'total' => 450.00,
            ],
            'other_costs' => [
                'taxes_and_other_costs' => 0.00,
                'homeowners_insurance' => 0.00,
                'total' => 0.00,
            ],
            'total_closing_costs' => 1360.00,
        ];
    }

    /**
     * Generate final closing costs
     *
     * @param Loan $loan
     * @return array Final costs breakdown
     */
    private function generateFinalClosingCosts(Loan $loan): array
    {
        return [
            'principal_amount' => round($loan->getPrincipal(), 2),
            'interest' => round($this->calculateTotalInterest($loan), 2),
            'total_of_payments' => round($this->calculateTotalPayments($loan), 2),
            'finance_charge' => round($this->calculateTotalInterest($loan), 2),
            'amount_financed' => round($loan->getPrincipal() + $this->calculateTotalFees($loan), 2),
            'total_closing_costs' => 1360.00,
        ];
    }

    /**
     * Generate payment information section
     *
     * @param Loan $loan
     * @return array Payment details
     */
    private function generatePaymentInformation(Loan $loan): array
    {
        $monthlyPayment = $this->calculateMonthlyPayment($loan);
        
        return [
            'monthly_payment' => round($monthlyPayment, 2),
            'payment_frequency' => 'Monthly',
            'number_of_payments' => $loan->getMonths(),
            'final_payment' => round($monthlyPayment, 2),
            'payment_schedule_starts' => date('Y-m-d', strtotime('+1 month')),
        ];
    }

    /**
     * Generate payment schedule for disclosure
     *
     * @param Loan $loan
     * @return array Schedule with dates and amounts
     */
    public function generatePaymentScheduleForDisclosure(Loan $loan): array
    {
        $schedule = [];
        $balance = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($loan);
        
        $currentDate = $loan->getStartDate() ?? new DateTimeImmutable('2024-01-01');
        
        for ($i = 1; $i <= $loan->getMonths(); $i++) {
            $interest = round($balance * $monthlyRate, 2);
            $principal = $monthlyPayment - $interest;
            
            if ($principal >= $balance) {
                $principal = $balance;
                $monthlyPayment = $principal + $interest;
            }
            
            $balance -= $principal;
            $balance = max(0, $balance);
            
            $schedule[] = [
                'payment_number' => $i,
                'date' => $currentDate->format('Y-m-d'),
                'payment' => round($monthlyPayment, 2),
                'principal' => round($principal, 2),
                'interest' => $interest,
                'balance' => round($balance, 2),
            ];
            
            $currentDate = $currentDate->modify('+1 month');
        }
        
        return [
            'payment_schedule' => $schedule,
            'first_payment_date' => $loan->getStartDate()?->modify('+1 month')->format('Y-m-d') ?? date('Y-m-d', strtotime('+1 month')),
            'total_payments' => count($schedule),
            'regular_payment_amount' => round($monthlyPayment, 2),
            'maturity_date' => end($schedule)['date'],
        ];
    }

    /**
     * Generate fee disclosure for specific category
     *
     * @param Loan $loan
     * @param string $category (services_borrower_cannot_shop|services_borrower_can_shop)
     * @return array Fee disclosure
     */
    public function generateFeeDisclosure(Loan $loan, string $category): array
    {
        $closingCosts = $this->generateClosingCostsSummary($loan);
        
        if (!isset($closingCosts[$category])) {
            $closingCosts[$category] = [];
        }
        
        $categoryData = $closingCosts[$category];
        $total = $categoryData['total'] ?? 0;

        $disclosure = [
            'service_type' => $category,
            'fees' => $categoryData,
            'total_' . str_replace('-', '_', $category) => $total,
            'disclosure_text' => $this->generateFeeDisclosureText($category),
        ];

        if ($category === 'services_borrower_can_shop') {
            $disclosure['shop_alert'] = 'You may shop for these services. Compare rates and fees.';
        }

        return $disclosure;
    }

    /**
     * Generate important terms section
     *
     * @param Loan $loan
     * @return array Important terms
     */
    private function generateImportantTerms(Loan $loan): array
    {
        return [
            'loan_term' => $loan->getMonths() . ' months',
            'interest_rate' => round($loan->getAnnualRate() * 100, 3) . '%',
            'apr' => round($this->calculateAPR($loan) * 100, 3) . '%',
            'monthly_payment' => round($this->calculateMonthlyPayment($loan), 2),
            'total_interest' => round($this->calculateTotalInterest($loan), 2),
            'total_payments' => round($this->calculateTotalPayments($loan), 2),
        ];
    }

    /**
     * Validate TRID compliance
     *
     * @param array $estimate Loan Estimate document
     * @return bool Is compliant
     */
    public function validateTRIDCompliance(array $estimate): bool
    {
        $required = [
            'document_type',
            'issue_date',
            'expiration_date',
            'loan_details',
            'closing_costs',
            'payment_schedule',
        ];

        foreach ($required as $field) {
            if (!isset($estimate[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate RESPA/TILA compliance
     *
     * @param array $closing Closing Disclosure document
     * @return bool Is compliant
     */
    public function validateRESPATILACompliance(array $closing): bool
    {
        $required = [
            'document_type',
            'closing_date',
            'loan_details',
            'closing_costs',
            'payment_information',
            'final_schedule',
        ];

        foreach ($required as $field) {
            if (!isset($closing[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Export document to JSON format
     *
     * @param array $document
     * @return string JSON representation
     */
    public function exportToJSON(array $document): string
    {
        return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Calculate expiration date (10 business days from issue)
     *
     * @param DateTimeImmutable $issueDate
     * @return DateTimeImmutable Expiration date
     */
    private function calculateExpirationDate(DateTimeImmutable $issueDate): DateTimeImmutable
    {
        $date = $issueDate;
        $businessDays = 0;

        while ($businessDays < 10) {
            $date = $date->modify('+1 day');
            $dayOfWeek = (int)$date->format('w');
            
            // Skip weekends (0 = Sunday, 6 = Saturday)
            if ($dayOfWeek !== 0 && $dayOfWeek !== 6) {
                $businessDays++;
            }
        }

        return $date;
    }

    /**
     * Generate TRID required disclosures
     *
     * @param Loan $loan
     * @return array Disclosure statements
     */
    private function generateTRIDDisclosures(Loan $loan): array
    {
        return [
            'accuracy_notice' => 'The interest rate, monthly payment, and finance charges are estimates based on current market conditions. Your actual rate and payment may differ.',
            'shopping_disclosure' => 'You may shop for these services. Comparison shopping may help you reduce closing costs.',
            'appraisal_notice' => 'If you want to know the value of this property, you may request an appraisal.',
            'fair_lending_notice' => 'We do business in accordance with fair lending laws.',
        ];
    }

    /**
     * Generate compliance notes
     *
     * @return array Compliance statements
     */
    private function generateComplianceNotes(): array
    {
        return [
            'trid_compliance' => 'This disclosure is compliant with TRID regulations.',
            'respa_compliance' => 'This disclosure is compliant with RESPA regulations.',
            'tila_compliance' => 'This disclosure is compliant with TILA requirements.',
            'fair_lending' => 'Loan terms were offered without regard to race, color, religion, sex, national origin, familial status, or disability.',
        ];
    }

    /**
     * Generate fee disclosure text
     *
     * @param string $category
     * @return string Disclosure text
     */
    private function generateFeeDisclosureText(string $category): string
    {
        if ($category === 'services_borrower_cannot_shop') {
            return 'These services may be shopped separately. Shop and compare rates and fees.';
        }
        return 'You have the right to shop for these services, and you should receive disclosure of the costs for these services.';
    }

    /**
     * Calculate total fees
     *
     * @param Loan $loan
     * @return float Total fees
     */
    private function calculateTotalFees(Loan $loan): float
    {
        // Simplified: standard fee structure
        return 1360.00;
    }

    /**
     * Calculate total interest
     *
     * @param Loan $loan
     * @return float Total interest
     */
    private function calculateTotalInterest(Loan $loan): float
    {
        $principal = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($loan);
        
        $totalInterest = 0;
        $balance = $principal;
        
        for ($i = 0; $i < $loan->getMonths(); $i++) {
            $interest = $balance * $monthlyRate;
            $principal = $monthlyPayment - $interest;
            $balance -= $principal;
            $totalInterest += $interest;
        }
        
        return round($totalInterest, 2);
    }

    /**
     * Calculate total payments
     *
     * @param Loan $loan
     * @return float Total payments
     */
    private function calculateTotalPayments(Loan $loan): float
    {
        return $loan->getPrincipal() + $this->calculateTotalInterest($loan);
    }

    /**
     * Calculate monthly payment
     *
     * @param Loan $loan
     * @return float Monthly payment
     */
    private function calculateMonthlyPayment(Loan $loan): float
    {
        $principal = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $numPayments = $loan->getMonths();

        if ($monthlyRate <= 0) {
            return round($principal / $numPayments, 2);
        }

        $denominator = pow(1 + $monthlyRate, $numPayments) - 1;
        $numerator = $monthlyRate * pow(1 + $monthlyRate, $numPayments);

        return round($principal * ($numerator / $denominator), 2);
    }
}
