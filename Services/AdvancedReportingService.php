<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

class AdvancedReportingService {

    /**
     * Generate amortization chart as HTML
     */
    public function generateAmortizationChart(Loan $loan, array $schedule): string {
        $html = "<table border='1'><tr><th>Period</th><th>Payment</th><th>Principal</th><th>Interest</th><th>Balance</th></tr>";
        
        foreach (array_slice($schedule, 0, 12) as $period => $payment) {
            $html .= "<tr><td>" . ($period + 1) . "</td>";
            $html .= "<td>$" . number_format($payment['payment'] ?? 0, 2) . "</td>";
            $html .= "<td>$" . number_format($payment['principal'] ?? 0, 2) . "</td>";
            $html .= "<td>$" . number_format($payment['interest'] ?? 0, 2) . "</td>";
            $html .= "<td>$" . number_format($payment['balance'] ?? 0, 2) . "</td></tr>";
        }
        
        $html .= "</table>";
        return $html;
    }

    /**
     * Generate payment trend chart data
     */
    public function generatePaymentTrendChart(array $schedule): array {
        $trends = ['payments' => [], 'principals' => [], 'interests' => [], 'periods' => []];
        
        foreach (array_slice($schedule, 0, 24) as $period => $payment) {
            $trends['periods'][] = $period + 1;
            $trends['payments'][] = $payment['payment'] ?? 0;
            $trends['principals'][] = $payment['principal'] ?? 0;
            $trends['interests'][] = $payment['interest'] ?? 0;
        }
        
        return $trends;
    }

    /**
     * Calculate total interest from schedule
     */
    public function calculateTotalInterest(array $schedule): float {
        $total = 0;
        foreach ($schedule as $period) {
            $total += $period['interest'] ?? 0;
        }
        return round($total, 2);
    }

    /**
     * Calculate total principal from schedule
     */
    public function calculateTotalPrincipal(array $schedule): float {
        $total = 0;
        foreach ($schedule as $period) {
            $total += $period['principal'] ?? 0;
        }
        return round($total, 2);
    }

    /**
     * Generate comprehensive financial summary
     */
    public function generateFinancialSummary(Loan $loan, array $schedule): array {
        $totalPayments = count($schedule);
        $totalInterest = $this->calculateTotalInterest($schedule);
        $totalPrincipal = $this->calculateTotalPrincipal($schedule);
        $totalCost = $loan->getPrincipal() + $totalInterest;

        return [
            'loan_amount' => round($loan->getPrincipal(), 2),
            'interest_rate' => round($loan->getAnnualRate() * 100, 2) . '%',
            'total_payments' => $totalPayments,
            'total_interest' => round($totalInterest, 2),
            'total_principal' => round($totalPrincipal, 2),
            'total_cost' => round($totalCost, 2),
            'average_payment' => round($totalCost / $totalPayments, 2),
            'interest_percentage' => round(($totalInterest / $totalCost) * 100, 2) . '%'
        ];
    }

    /**
     * Export schedule to CSV format
     */
    public function exportToCSV(array $schedule): string {
        $csv = "Period,Payment,Principal,Interest,Balance\n";
        
        foreach ($schedule as $period => $payment) {
            $csv .= ($period + 1) . ',';
            $csv .= ($payment['payment'] ?? 0) . ',';
            $csv .= ($payment['principal'] ?? 0) . ',';
            $csv .= ($payment['interest'] ?? 0) . ',';
            $csv .= ($payment['balance'] ?? 0) . "\n";
        }
        
        return $csv;
    }

    /**
     * Export schedule to JSON format
     */
    public function exportToJSON(Loan $loan, array $schedule): string {
        $data = [
            'loan_id' => $loan->getId(),
            'principal' => $loan->getPrincipal(),
            'rate' => $loan->getAnnualRate(),
            'months' => $loan->getMonths(),
            'schedule' => $schedule
        ];
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Generate HTML report
     */
    public function generateHTML(Loan $loan, array $schedule): string {
        $summary = $this->generateFinancialSummary($loan, $schedule);
        $chart = $this->generateAmortizationChart($loan, $schedule);
        
        $html = "<html><body>";
        $html .= "<h1>Loan Amortization Report</h1>";
        $html .= "<h2>Summary</h2>";
        foreach ($summary as $key => $value) {
            $html .= "<p><strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> " . htmlspecialchars($value) . "</p>";
        }
        $html .= "<h2>Payment Schedule</h2>";
        $html .= $chart;
        $html .= "</body></html>";
        
        return $html;
    }

    /**
     * Generate monthly analysis
     */
    public function generateMonthlyAnalysis(array $schedule): array {
        $analysis = [
            'total_periods' => count($schedule),
            'monthly_average_payment' => 0,
            'monthly_average_principal' => 0,
            'monthly_average_interest' => 0
        ];
        
        if (!empty($schedule)) {
            $analysis['monthly_average_payment'] = round(array_sum(array_map(function($p) { return $p['payment'] ?? 0; }, $schedule)) / count($schedule), 2);
            $analysis['monthly_average_principal'] = round(array_sum(array_map(function($p) { return $p['principal'] ?? 0; }, $schedule)) / count($schedule), 2);
            $analysis['monthly_average_interest'] = round(array_sum(array_map(function($p) { return $p['interest'] ?? 0; }, $schedule)) / count($schedule), 2);
        }
        
        return $analysis;
    }

    /**
     * Calculate interest accrual over time
     */
    public function calculateInterestAccrual(array $schedule): array {
        $accrual = [];
        $totalInterest = 0;
        
        foreach ($schedule as $period => $payment) {
            $totalInterest += $payment['interest'] ?? 0;
            $accrual[$period] = round($totalInterest, 2);
        }
        
        return $accrual;
    }

    /**
     * Summarize payment history
     */
    public function summarizePaymentHistory(array $schedule): array {
        return [
            'total_payments_scheduled' => count($schedule),
            'first_payment_amount' => $schedule[0]['payment'] ?? 0,
            'last_payment_amount' => $schedule[count($schedule) - 1]['payment'] ?? 0,
            'payment_variance' => round(max(array_map(function($p) { return $p['payment'] ?? 0; }, $schedule)) - min(array_map(function($p) { return $p['payment'] ?? 0; }, $schedule)), 2)
        ];
    }

    /**
     * Visualize payment schedule structure
     */
    public function visualizePaymentSchedule(array $schedule): array {
        $visualization = [];
        
        foreach (array_slice($schedule, 0, 12) as $period => $payment) {
            $visualization['period_' . ($period + 1)] = [
                'payment' => round($payment['payment'] ?? 0, 2),
                'principal_percent' => ($payment['payment'] ?? 0) > 0 ? round((($payment['principal'] ?? 0) / ($payment['payment'] ?? 0)) * 100, 1) : 0,
                'interest_percent' => ($payment['payment'] ?? 0) > 0 ? round((($payment['interest'] ?? 0) / ($payment['payment'] ?? 0)) * 100, 1) : 0
            ];
        }
        
        return $visualization;
    }

    /**
     * Generate comparison report between two schedules
     */
    public function generateComparisonReport(array $schedule1, array $schedule2): array {
        return [
            'schedule_1_total_interest' => round(array_sum(array_map(function($p) { return $p['interest'] ?? 0; }, $schedule1)), 2),
            'schedule_2_total_interest' => round(array_sum(array_map(function($p) { return $p['interest'] ?? 0; }, $schedule2)), 2),
            'interest_savings' => round(array_sum(array_map(function($p) { return $p['interest'] ?? 0; }, $schedule1)) - array_sum(array_map(function($p) { return $p['interest'] ?? 0; }, $schedule2)), 2),
            'schedule_1_total_periods' => count($schedule1),
            'schedule_2_total_periods' => count($schedule2),
            'period_difference' => count($schedule1) - count($schedule2)
        ];
    }

    /**
     * Export to XML format
     */
    public function exportToXML(Loan $loan, array $schedule): string {
        $xml = "<?xml version='1.0'?>\n<loan>\n";
        $xml .= "  <principal>" . htmlspecialchars($loan->getPrincipal()) . "</principal>\n";
        $xml .= "  <rate>" . htmlspecialchars($loan->getAnnualRate()) . "</rate>\n";
        $xml .= "  <months>" . htmlspecialchars($loan->getMonths()) . "</months>\n";
        $xml .= "  <schedule>\n";
        
        foreach ($schedule as $period => $payment) {
            $xml .= "    <payment>\n";
            $xml .= "      <period>" . ($period + 1) . "</period>\n";
            $xml .= "      <amount>" . htmlspecialchars($payment['payment'] ?? 0) . "</amount>\n";
            $xml .= "      <principal>" . htmlspecialchars($payment['principal'] ?? 0) . "</principal>\n";
            $xml .= "      <interest>" . htmlspecialchars($payment['interest'] ?? 0) . "</interest>\n";
            $xml .= "      <balance>" . htmlspecialchars($payment['balance'] ?? 0) . "</balance>\n";
            $xml .= "    </payment>\n";
        }
        
        $xml .= "  </schedule>\n</loan>";
        return $xml;
    }
}
