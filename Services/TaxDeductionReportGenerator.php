<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTime;

/**
 * TaxDeductionReportGenerator
 *
 * Generates annual tax deduction reports with interest itemization and
 * mortgage interest deduction limit calculations for tax filing purposes.
 */
class TaxDeductionReportGenerator
{
    /**
     * @var DecimalCalculator
     */
    private $calculator;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Generate annual tax deduction report for a loan
     */
    public function generateAnnualTaxDeductionReport(Loan $loan, array $schedule, int $year): array
    {
        $totalInterest = $this->calculateTotalInterestForYear($schedule, $year);
        $monthlyBreakdown = $this->generateMonthlyBreakdown($schedule, $year);
        $cumulativeInterest = $this->calculateCumulativeInterestThroughYear($schedule, $year);

        return [
            'year' => $year,
            'loan_id' => $loan->getId(),
            'total_interest' => $this->calculator->round($totalInterest, 2),
            'monthly_breakdown' => $monthlyBreakdown,
            'cumulative_interest' => $cumulativeInterest,
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate total interest paid during a calendar year
     */
    public function calculateTotalInterestForYear(array $schedule, int $year): float
    {
        $totalInterest = 0;

        foreach ($schedule as $payment) {
            $paymentDate = new DateTime($payment['date']);
            if ((int)$paymentDate->format('Y') === $year) {
                $totalInterest += $payment['interest'];
            }
        }

        return $this->calculator->round($totalInterest, 2);
    }

    /**
     * Generate monthly breakdown of interest payments for a year
     */
    public function generateMonthlyBreakdown(array $schedule, int $year): array
    {
        $breakdown = [];

        foreach ($schedule as $payment) {
            $paymentDate = new DateTime($payment['date']);
            if ((int)$paymentDate->format('Y') === $year) {
                $breakdown[] = [
                    'month' => (int)$paymentDate->format('m'),
                    'date' => $payment['date'],
                    'interest' => $this->calculator->round($payment['interest'], 2),
                    'principal' => $this->calculator->round($payment['principal'], 2),
                    'payment' => $this->calculator->round($payment['payment'], 2),
                    'balance' => $this->calculator->round($payment['balance'], 2),
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Calculate cumulative interest through each month of the year
     */
    public function calculateCumulativeInterestThroughYear(array $schedule, int $year): array
    {
        $cumulative = [];
        $totalInterest = 0;

        foreach ($schedule as $payment) {
            $paymentDate = new DateTime($payment['date']);
            if ((int)$paymentDate->format('Y') === $year) {
                $totalInterest += $payment['interest'];
                $cumulative[] = $this->calculator->round($totalInterest, 2);
            }
        }

        return $cumulative;
    }

    /**
     * Generate tax summary with itemized deductions
     */
    public function generateTaxSummary(Loan $loan, array $schedule, int $year): array
    {
        $totalInterest = $this->calculateTotalInterestForYear($schedule, $year);
        $deductibleInterest = $this->calculateMortgageInterestDeductionLimit(
            $loan->getPrincipal(),
            $totalInterest
        );

        return [
            'year' => $year,
            'loan_id' => $loan->getId(),
            'total_interest_paid' => $this->calculator->round($totalInterest, 2),
            'total_interest_deductible' => $this->calculator->round($deductibleInterest, 2),
            'itemized_deductions' => [
                'mortgage_interest' => $this->calculator->round($deductibleInterest, 2),
                'property_taxes' => 0,
                'state_local_taxes' => 0,
                'charitable' => 0,
            ],
            'loan_info' => [
                'original_balance' => $this->calculator->round($loan->getPrincipal(), 2),
                'rate' => $loan->getAnnualRate(),
                'type' => 'mortgage',
            ],
            'deduction_limit_applied' => $loan->getPrincipal() > 750000,
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate mortgage interest deduction limit based on IRS rules
     */
    public function calculateMortgageInterestDeductionLimit(float $originalBalance, float $totalInterest): float
    {
        $deductionLimit = 750000;

        if ($originalBalance <= $deductionLimit) {
            return $this->calculator->round($totalInterest, 2);
        }

        $ratio = $this->calculator->divide($deductionLimit, $originalBalance);
        $deductibleInterest = $this->calculator->multiply($totalInterest, $ratio);

        return $this->calculator->round($deductibleInterest, 2);
    }

    /**
     * Export tax report to JSON format
     */
    public function exportToJSON(array $report): string
    {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate multi-year tax deduction reports
     */
    public function generateMultiYearTaxDeductionReport(
        Loan $loan,
        array $schedule,
        int $startYear,
        int $endYear
    ): array {
        $reports = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $reports[] = $this->generateAnnualTaxDeductionReport($loan, $schedule, $year);
        }

        return $reports;
    }

    /**
     * Generate tax deduction projection for multiple years
     */
    public function generateTaxEstimateProjection(
        Loan $loan,
        array $schedule,
        int $startYear,
        int $yearCount
    ): array {
        $projection = [];

        for ($i = 0; $i < $yearCount; $i++) {
            $year = $startYear + $i;
            $projection[] = $this->generateAnnualTaxDeductionReport($loan, $schedule, $year);
        }

        return $projection;
    }

    /**
     * Validate tax deduction report compliance
     */
    public function validateTaxDeductionCompliance(array $report): array
    {
        $issues = [];

        if (!isset($report['year']) || !is_numeric($report['year'])) {
            $issues[] = 'Invalid or missing year field';
        }

        if (!isset($report['total_interest']) || $report['total_interest'] < 0) {
            $issues[] = 'Invalid or missing total_interest field';
        }

        if (!isset($report['monthly_breakdown']) || !is_array($report['monthly_breakdown'])) {
            $issues[] = 'Invalid or missing monthly_breakdown field';
        }

        return [
            'compliant' => count($issues) === 0,
            'issues' => $issues,
            'validated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }
}
