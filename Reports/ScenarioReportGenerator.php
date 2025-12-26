<?php

namespace Ksfraser\Amortizations\Reports;

use Ksfraser\Amortizations\Services\ScenarioAnalysisService;
use Ksfraser\Amortizations\Models\Loan;

/**
 * ScenarioReportGenerator - Generate Scenario Analysis Reports
 *
 * Generates on-screen and PDF reports for what-if scenario analysis:
 * - Single scenario detailed report
 * - Scenario comparison reports (side-by-side)
 * - Payment strategy impact analysis
 * - Savings calculations
 *
 * Supports multiple output formats:
 * - HTML (for on-screen display)
 * - CSV (for spreadsheet export)
 * - PDF (for formal reports)
 *
 * @package    Ksfraser\Amortizations\Reports
 * @author     Development Team
 * @since      Phase 10
 * @version    1.0.0
 */
class ScenarioReportGenerator
{
    /**
     * @var ScenarioAnalysisService
     */
    private $scenarioService;

    public function __construct(ScenarioAnalysisService $scenarioService)
    {
        $this->scenarioService = $scenarioService;
    }

    /**
     * Generate HTML report for single scenario
     *
     * @param array $scenario Scenario configuration from ScenarioAnalysisService
     * @param array $schedule Generated schedule from generateScenarioSchedule
     * @param Loan $loan Base loan object
     * @return string HTML report
     */
    public function generateScenarioHtmlReport(array $scenario, array $schedule, Loan $loan): string
    {
        $html = '';

        // Report header
        $html .= $this->generateReportHeader($scenario, $loan);

        // Scenario summary
        $html .= $this->generateScenarioSummary($scenario, $schedule);

        // Detailed schedule table
        $html .= $this->generateScheduleTable($schedule);

        // Calculations summary
        $html .= $this->generateCalculationsSummary($schedule);

        // Key metrics
        $html .= $this->generateKeyMetrics($schedule, $loan);

        return $html;
    }

    /**
     * Generate HTML report comparing two scenarios
     *
     * @param array $scenario1 First scenario
     * @param array $schedule1 First scenario schedule
     * @param array $scenario2 Second scenario
     * @param array $schedule2 Second scenario schedule
     * @param Loan $loan Base loan
     * @return string Comparison HTML report
     */
    public function generateComparisonHtmlReport(
        array $scenario1,
        array $schedule1,
        array $scenario2,
        array $schedule2,
        Loan $loan
    ): string {
        $html = '';

        // Comparison header
        $html .= '<div class="scenario-comparison-report">';
        $html .= '<h2>Loan Scenario Comparison Report</h2>';
        $html .= '<p><strong>Loan:</strong> ' . htmlspecialchars($loan->getLoanNumber()) . '</p>';
        $html .= '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '</div>';

        // Scenario summaries side-by-side
        $html .= '<div class="scenario-comparison-summaries">';
        $html .= '<div class="scenario-summary-col">';
        $html .= '<h3>' . htmlspecialchars($scenario1['name']) . '</h3>';
        $html .= $this->generateScenarioSummary($scenario1, $schedule1);
        $html .= '</div>';

        $html .= '<div class="scenario-summary-col">';
        $html .= '<h3>' . htmlspecialchars($scenario2['name']) . '</h3>';
        $html .= $this->generateScenarioSummary($scenario2, $schedule2);
        $html .= '</div>';
        $html .= '</div>';

        // Comparison metrics table
        $html .= $this->generateComparisonMetricsTable($schedule1, $schedule2);

        // Savings analysis
        $html .= $this->generateSavingsAnalysis($schedule1, $schedule2);

        return $html;
    }

    /**
     * Generate report header
     *
     * @param array $scenario
     * @param Loan $loan
     * @return string HTML
     */
    private function generateReportHeader(array $scenario, Loan $loan): string
    {
        $modifications = $scenario['modifications'];
        $html = '<div class="scenario-report-header">';
        $html .= '<h2>Loan Scenario Analysis Report</h2>';
        $html .= '<p><strong>Loan Number:</strong> ' . htmlspecialchars($loan->getLoanNumber()) . '</p>';
        $html .= '<p><strong>Scenario Name:</strong> ' . htmlspecialchars($scenario['name']) . '</p>';
        $html .= '<p><strong>Original Loan Amount:</strong> $' . number_format($loan->getOriginalAmount(), 2) . '</p>';
        $html .= '<p><strong>Annual Interest Rate:</strong> ' . number_format($loan->getAnnualRate() * 100, 2) . '%</p>';
        $html .= '<p><strong>Loan Term:</strong> ' . $loan->getMonths() . ' months</p>';
        $html .= '<p><strong>Report Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';

        // Modifications applied
        if (!empty($modifications)) {
            $html .= '<h3>Scenario Modifications</h3>';
            $html .= '<ul>';
            if (!empty($modifications['extra_monthly_payment'])) {
                $html .= '<li>Extra Monthly Payment: $' . number_format($modifications['extra_monthly_payment'], 2) . '</li>';
            }
            if (!empty($modifications['lump_sum_payment'])) {
                $html .= '<li>Lump Sum Payment: $' . number_format($modifications['lump_sum_payment'], 2);
                if (!empty($modifications['lump_sum_month'])) {
                    $html .= ' at Period ' . (int)$modifications['lump_sum_month'];
                }
                $html .= '</li>';
            }
            if (!empty($modifications['skip_payment_period'])) {
                $html .= '<li>Skip Payment at Period: ' . (int)$modifications['skip_payment_period'] . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Generate scenario summary metrics
     *
     * @param array $scenario
     * @param array $schedule
     * @return string HTML
     */
    private function generateScenarioSummary(array $scenario, array $schedule): string
    {
        $periods = $schedule['periods'];
        $totalInterest = 0;
        $totalPayments = 0;
        $totalPrincipal = 0;

        foreach ($periods as $period) {
            $totalInterest += $period['interest'];
            $totalPayments += $period['payment'];
            $totalPrincipal += $period['principal'];
        }

        $html = '<div class="scenario-summary">';
        $html .= '<table class="summary-table">';
        $html .= '<tr><td><strong>Total Periods:</strong></td><td>' . count($periods) . '</td></tr>';
        $html .= '<tr><td><strong>Total Payments:</strong></td><td>$' . number_format($totalPayments, 2) . '</td></tr>';
        $html .= '<tr><td><strong>Total Principal:</strong></td><td>$' . number_format($totalPrincipal, 2) . '</td></tr>';
        $html .= '<tr><td><strong>Total Interest:</strong></td><td>$' . number_format($totalInterest, 2) . '</td></tr>';
        if (!empty($periods)) {
            $html .= '<tr><td><strong>Final Payment Date:</strong></td><td>' . $periods[count($periods)-1]['date'] . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate detailed amortization schedule table
     *
     * @param array $schedule
     * @return string HTML table
     */
    private function generateScheduleTable(array $schedule): string
    {
        $html = '<div class="schedule-table-wrapper">';
        $html .= '<h3>Amortization Schedule</h3>';
        $html .= '<table class="amortization-schedule" border="1">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Period</th>';
        $html .= '<th>Date</th>';
        $html .= '<th>Payment</th>';
        $html .= '<th>Principal</th>';
        $html .= '<th>Interest</th>';
        $html .= '<th>Balance</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($schedule['periods'] as $period) {
            $html .= '<tr>';
            $html .= '<td>' . $period['period'] . '</td>';
            $html .= '<td>' . $period['date'] . '</td>';
            $html .= '<td>$' . number_format($period['payment'], 2) . '</td>';
            $html .= '<td>$' . number_format($period['principal'], 2) . '</td>';
            $html .= '<td>$' . number_format($period['interest'], 2) . '</td>';
            $html .= '<td>$' . number_format($period['balance'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate calculations summary
     *
     * @param array $schedule
     * @return string HTML
     */
    private function generateCalculationsSummary(array $schedule): string
    {
        $periods = $schedule['periods'];
        $totalInterest = 0;
        $totalPayments = 0;

        foreach ($periods as $period) {
            $totalInterest += $period['interest'];
            $totalPayments += $period['payment'];
        }

        $html = '<div class="calculations-summary">';
        $html .= '<h3>Summary Calculations</h3>';
        $html .= '<table>';
        $html .= '<tr><td><strong>Total Payments:</strong></td><td>$' . number_format($totalPayments, 2) . '</td></tr>';
        $html .= '<tr><td><strong>Total Interest Paid:</strong></td><td>$' . number_format($totalInterest, 2) . '</td></tr>';
        $html .= '<tr><td><strong>Time to Payoff:</strong></td><td>' . count($periods) . ' months (' . round(count($periods)/12, 1) . ' years)</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate key metrics section
     *
     * @param array $schedule
     * @param Loan $loan
     * @return string HTML
     */
    private function generateKeyMetrics(array $schedule, Loan $loan): string
    {
        $periods = $schedule['periods'];
        $totalInterest = array_sum(array_column($periods, 'interest'));
        $totalPayments = array_sum(array_column($periods, 'payment'));

        $originalMonths = $loan->getMonths();
        $actualMonths = count($periods);
        $monthsSaved = $originalMonths - $actualMonths;

        $html = '<div class="key-metrics">';
        $html .= '<h3>Key Performance Metrics</h3>';
        $html .= '<table>';
        $html .= '<tr><td><strong>Payoff Time Reduction:</strong></td><td>' . $monthsSaved . ' months (' . round($monthsSaved/12, 1) . ' years)</td></tr>';
        $html .= '<tr><td><strong>Interest Savings:</strong></td><td>$' . number_format($totalInterest, 2) . '</td></tr>';
        $html .= '<tr><td><strong>Effective Payoff Date:</strong></td><td>' . $periods[count($periods)-1]['date'] . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate comparison metrics table
     *
     * @param array $schedule1
     * @param array $schedule2
     * @return string HTML
     */
    private function generateComparisonMetricsTable(array $schedule1, array $schedule2): string
    {
        $calc = function($schedule) {
            return [
                'periods' => count($schedule['periods']),
                'total_interest' => array_sum(array_column($schedule['periods'], 'interest')),
                'total_payments' => array_sum(array_column($schedule['periods'], 'payment')),
                'final_date' => end($schedule['periods'])['date'] ?? '',
            ];
        };

        $metrics1 = $calc($schedule1);
        $metrics2 = $calc($schedule2);

        $interestDiff = $metrics2['total_interest'] - $metrics1['total_interest'];
        $periodsDiff = $metrics2['periods'] - $metrics1['periods'];

        $html = '<div class="comparison-metrics">';
        $html .= '<h3>Comparison Summary</h3>';
        $html .= '<table class="comparison-table" border="1">';
        $html .= '<thead><tr><th>Metric</th><th>Scenario 1</th><th>Scenario 2</th><th>Difference</th></tr></thead>';
        $html .= '<tbody>';
        $html .= '<tr>';
        $html .= '<td><strong>Total Periods</strong></td>';
        $html .= '<td>' . $metrics1['periods'] . '</td>';
        $html .= '<td>' . $metrics2['periods'] . '</td>';
        $html .= '<td>' . ($periodsDiff > 0 ? '+' : '') . $periodsDiff . ' months</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td><strong>Total Interest</strong></td>';
        $html .= '<td>$' . number_format($metrics1['total_interest'], 2) . '</td>';
        $html .= '<td>$' . number_format($metrics2['total_interest'], 2) . '</td>';
        $html .= '<td>$' . number_format($interestDiff, 2) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td><strong>Total Payments</strong></td>';
        $html .= '<td>$' . number_format($metrics1['total_payments'], 2) . '</td>';
        $html .= '<td>$' . number_format($metrics2['total_payments'], 2) . '</td>';
        $html .= '<td>$' . number_format($metrics2['total_payments'] - $metrics1['total_payments'], 2) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td><strong>Final Payoff Date</strong></td>';
        $html .= '<td>' . $metrics1['final_date'] . '</td>';
        $html .= '<td>' . $metrics2['final_date'] . '</td>';
        $html .= '<td>-</td>';
        $html .= '</tr>';
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate savings analysis
     *
     * @param array $schedule1
     * @param array $schedule2
     * @return string HTML
     */
    private function generateSavingsAnalysis(array $schedule1, array $schedule2): string
    {
        $interest1 = array_sum(array_column($schedule1['periods'], 'interest'));
        $interest2 = array_sum(array_column($schedule2['periods'], 'interest'));
        $savings = abs($interest2 - $interest1);
        $savingsPercentage = ($interest1 > 0) ? ($savings / $interest1 * 100) : 0;

        $html = '<div class="savings-analysis">';
        $html .= '<h3>Interest Savings Analysis</h3>';

        if ($interest2 < $interest1) {
            $html .= '<div class="savings-positive">';
            $html .= '<p><strong>Interest Savings:</strong> $' . number_format($savings, 2) . ' (' . number_format($savingsPercentage, 1) . '%)</p>';
            $html .= '<p>Scenario 2 results in lower interest costs.</p>';
            $html .= '</div>';
        } else if ($interest2 > $interest1) {
            $html .= '<div class="savings-negative">';
            $html .= '<p><strong>Additional Cost:</strong> $' . number_format($savings, 2) . ' (' . number_format($savingsPercentage, 1) . '%)</p>';
            $html .= '<p>Scenario 2 results in higher interest costs.</p>';
            $html .= '</div>';
        } else {
            $html .= '<div class="savings-neutral">';
            $html .= '<p><strong>No Difference:</strong> Both scenarios have equal interest costs.</p>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Generate CSV export of scenario
     *
     * @param array $scenario
     * @param array $schedule
     * @return string CSV content
     */
    public function generateScenarioCsv(array $scenario, array $schedule): string
    {
        $csv = '';

        // Header info
        $csv .= "Scenario Analysis Report\n";
        $csv .= "Scenario Name," . $scenario['name'] . "\n";
        $csv .= "Created," . $scenario['created_at'] . "\n\n";

        // Schedule header
        $csv .= "Period,Date,Payment,Principal,Interest,Balance\n";

        // Schedule rows
        foreach ($schedule['periods'] as $period) {
            $csv .= $period['period'] . ',';
            $csv .= $period['date'] . ',';
            $csv .= $period['payment'] . ',';
            $csv .= $period['principal'] . ',';
            $csv .= $period['interest'] . ',';
            $csv .= $period['balance'] . "\n";
        }

        return $csv;
    }
}
