<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTime;

/**
 * DocumentGenerationService
 *
 * Generates amortization schedules in multiple formats (PDF, Excel, CSV, HTML, JSON)
 * with formatting, custom headers, and compliance validation.
 */
class DocumentGenerationService
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
     * Generate amortization schedule as CSV
     */
    public function generateAmortizationScheduleAsCSV(Loan $loan, array $schedule): string
    {
        $csv = "Month,Date,Payment,Principal,Interest,Balance\n";

        foreach ($schedule as $row) {
            $csv .= sprintf(
                "%d,%s,%.2f,%.2f,%.2f,%.2f\n",
                $row['month'],
                $row['date'],
                $row['payment'],
                $row['principal'],
                $row['interest'],
                $row['balance']
            );
        }

        return $csv;
    }

    /**
     * Generate amortization schedule as Excel
     */
    public function generateAmortizationScheduleAsExcel(Loan $loan, array $schedule): string
    {
        // Simplified: return JSON-encoded data that can be consumed by Excel libraries
        $data = [
            'loan_info' => [
                'id' => $loan->getId(),
                'principal' => $loan->getPrincipal(),
                'rate' => $loan->getAnnualRate(),
                'months' => $loan->getMonths(),
            ],
            'schedule' => $schedule,
        ];

        // Serialize as pseudo-Excel format (tab-separated for compatibility)
        $excel = "Loan Amortization Schedule\n\n";
        $excel .= "Principal: " . $loan->getPrincipal() . "\n";
        $excel .= "Rate: " . ($loan->getAnnualRate() * 100) . "%\n";
        $excel .= "Months: " . $loan->getMonths() . "\n\n";

        $excel .= "Month\tDate\tPayment\tPrincipal\tInterest\tBalance\n";
        foreach ($schedule as $row) {
            $excel .= sprintf(
                "%d\t%s\t%.2f\t%.2f\t%.2f\t%.2f\n",
                $row['month'],
                $row['date'],
                $row['payment'],
                $row['principal'],
                $row['interest'],
                $row['balance']
            );
        }

        return $excel;
    }

    /**
     * Generate loan summary document
     */
    public function generateLoanSummaryDocument(Loan $loan, array $schedule): array
    {
        $totalInterest = 0;
        $totalPayments = 0;

        foreach ($schedule as $row) {
            $totalInterest += $row['interest'];
            $totalPayments += $row['payment'];
        }

        return [
            'loan_id' => $loan->getId(),
            'principal' => $this->calculator->round($loan->getPrincipal(), 2),
            'rate' => $this->calculator->round($loan->getAnnualRate() * 100, 2),
            'months' => $loan->getMonths(),
            'start_date' => $loan->getStartDate()->format('Y-m-d'),
            'monthly_payment' => $this->calculator->round($schedule[0]['payment'], 2),
            'total_payments' => $this->calculator->round($totalPayments, 2),
            'total_interest' => $this->calculator->round($totalInterest, 2),
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate payment schedule with custom headers
     */
    public function generatePaymentScheduleWithCustomHeaders(Loan $loan, array $schedule, array $customHeaders): array
    {
        $rows = [];

        foreach ($schedule as $payment) {
            $row = [];
            foreach ($customHeaders as $headerLabel => $dataKey) {
                $row[$headerLabel] = $payment[$dataKey] ?? null;
            }
            $rows[] = $row;
        }

        return [
            'headers' => array_keys($customHeaders),
            'rows' => $rows,
            'loan_id' => $loan->getId(),
        ];
    }

    /**
     * Export to HTML format
     */
    public function exportToHTML(Loan $loan, array $schedule): string
    {
        $html = "<html><head><title>Amortization Schedule - Loan {$loan->getId()}</title>";
        $html .= "<style>table { border-collapse: collapse; width: 100%; }";
        $html .= "th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }";
        $html .= "th { background-color: #4CAF50; color: white; }";
        $html .= "tr:nth-child(even) { background-color: #f2f2f2; }</style></head><body>";

        $html .= "<h1>Loan Amortization Schedule</h1>";
        $html .= "<p>Loan ID: {$loan->getId()} | Principal: \$" . number_format($loan->getPrincipal(), 2) . " | Rate: " . ($loan->getAnnualRate() * 100) . "%</p>";

        $html .= "<table><thead><tr>";
        $html .= "<th>Month</th><th>Date</th><th>Payment</th><th>Principal</th><th>Interest</th><th>Balance</th>";
        $html .= "</tr></thead><tbody>";

        foreach ($schedule as $row) {
            $html .= "<tr>";
            $html .= "<td>" . $row['month'] . "</td>";
            $html .= "<td>" . $row['date'] . "</td>";
            $html .= "<td>\$" . number_format($row['payment'], 2) . "</td>";
            $html .= "<td>\$" . number_format($row['principal'], 2) . "</td>";
            $html .= "<td>\$" . number_format($row['interest'], 2) . "</td>";
            $html .= "<td>\$" . number_format($row['balance'], 2) . "</td>";
            $html .= "</tr>";
        }

        $html .= "</tbody></table></body></html>";
        return $html;
    }

    /**
     * Export to JSON
     */
    public function exportToJSON(Loan $loan, array $schedule): string
    {
        $data = [
            'loan_info' => [
                'id' => $loan->getId(),
                'principal' => $loan->getPrincipal(),
                'rate' => $loan->getAnnualRate(),
                'months' => $loan->getMonths(),
                'start_date' => $loan->getStartDate()->format('Y-m-d'),
            ],
            'schedule' => $schedule,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate comparison schedule
     */
    public function generateComparisonSchedule(Loan $loan, array $schedule1, array $schedule2, string $comparisonType): array
    {
        $totalInterest1 = array_sum(array_column($schedule1, 'interest'));
        $totalInterest2 = array_sum(array_column($schedule2, 'interest'));

        return [
            'loan_id' => $loan->getId(),
            'comparison_type' => $comparisonType,
            'schedules' => [
                'schedule_1' => [
                    'months' => count($schedule1),
                    'total_interest' => $this->calculator->round($totalInterest1, 2),
                ],
                'schedule_2' => [
                    'months' => count($schedule2),
                    'total_interest' => $this->calculator->round($totalInterest2, 2),
                ],
            ],
            'savings' => $this->calculator->round(max(0, $totalInterest1 - $totalInterest2), 2),
        ];
    }

    /**
     * Add formatting to document
     * 
     * Applies formatting options like fonts, colors, margins, headers/footers
     * 
     * @param array $document Document structure
     * @param array $formatOptions Formatting configuration
     * @return array Document with applied formatting
     */
    public function addFormattingToDocument(array $document, array $formatOptions): array
    {
        // Initialize formatting structure if not exists
        if (!isset($document['formatting'])) {
            $document['formatting'] = [];
        }
        
        // Apply font settings
        if (isset($formatOptions['font'])) {
            $document['formatting']['font'] = [
                'family' => $formatOptions['font']['family'] ?? 'Arial',
                'size' => $formatOptions['font']['size'] ?? 12,
                'color' => $formatOptions['font']['color'] ?? '#000000'
            ];
        }
        
        // Apply page settings
        if (isset($formatOptions['page'])) {
            $document['formatting']['page'] = [
                'size' => $formatOptions['page']['size'] ?? 'A4',
                'orientation' => $formatOptions['page']['orientation'] ?? 'portrait',
                'margin_top' => $formatOptions['page']['margin_top'] ?? 25,
                'margin_right' => $formatOptions['page']['margin_right'] ?? 25,
                'margin_bottom' => $formatOptions['page']['margin_bottom'] ?? 25,
                'margin_left' => $formatOptions['page']['margin_left'] ?? 25
            ];
        }
        
        // Apply header settings
        if (isset($formatOptions['header'])) {
            $document['formatting']['header'] = [
                'enabled' => $formatOptions['header']['enabled'] ?? true,
                'content' => $formatOptions['header']['content'] ?? '',
                'logo' => $formatOptions['header']['logo'] ?? null,
                'height' => $formatOptions['header']['height'] ?? 50
            ];
        }
        
        // Apply footer settings
        if (isset($formatOptions['footer'])) {
            $document['formatting']['footer'] = [
                'enabled' => $formatOptions['footer']['enabled'] ?? true,
                'content' => $formatOptions['footer']['content'] ?? 'Page {PAGE_NUM} of {PAGE_COUNT}',
                'height' => $formatOptions['footer']['height'] ?? 30
            ];
        }
        
        // Apply styling/theme
        if (isset($formatOptions['theme'])) {
            $document['formatting']['theme'] = [
                'primary_color' => $formatOptions['theme']['primary_color'] ?? '#007bff',
                'secondary_color' => $formatOptions['theme']['secondary_color'] ?? '#6c757d',
                'heading_color' => $formatOptions['theme']['heading_color'] ?? '#212529',
                'border_color' => $formatOptions['theme']['border_color'] ?? '#dee2e6'
            ];
        }
        
        // Apply table styling
        if (isset($formatOptions['tables'])) {
            $document['formatting']['tables'] = [
                'border_width' => $formatOptions['tables']['border_width'] ?? 1,
                'cell_padding' => $formatOptions['tables']['cell_padding'] ?? 8,
                'header_bg' => $formatOptions['tables']['header_bg'] ?? '#f8f9fa',
                'stripe_rows' => $formatOptions['tables']['stripe_rows'] ?? true
            ];
        }
        
        return $document;
    }

    /**
     * Generate payment coupon for specific month
     */
    public function generatePaymentCoupon(Loan $loan, array $paymentRow): array
    {
        return [
            'loan_id' => $loan->getId(),
            'month' => $paymentRow['month'],
            'due_date' => $paymentRow['date'],
            'payment_amount' => $this->calculator->round($paymentRow['payment'], 2),
            'breakdown' => [
                'principal' => $this->calculator->round($paymentRow['principal'], 2),
                'interest' => $this->calculator->round($paymentRow['interest'], 2),
            ],
            'remaining_balance' => $this->calculator->round($paymentRow['balance'], 2),
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate year-end statement
     */
    public function generateYearEndStatement(Loan $loan, array $schedule, int $year): array
    {
        $yearPayments = [];
        $totalPaid = 0;
        $totalInterest = 0;

        foreach ($schedule as $payment) {
            $paymentDate = new DateTime($payment['date']);
            if ((int)$paymentDate->format('Y') === $year) {
                $yearPayments[] = $payment;
                $totalPaid += $payment['payment'];
                $totalInterest += $payment['interest'];
            }
        }

        return [
            'loan_id' => $loan->getId(),
            'year' => $year,
            'total_paid' => $this->calculator->round($totalPaid, 2),
            'total_principal' => $this->calculator->round(
                array_sum(array_column($yearPayments, 'principal')),
                2
            ),
            'total_interest' => $this->calculator->round($totalInterest, 2),
            'payment_count' => count($yearPayments),
            'tax_summary' => [
                'deductible_interest' => $this->calculator->round($totalInterest, 2),
            ],
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate document bundle
     */
    public function generateDocumentBundle(Loan $loan, array $schedule, array $formats): array
    {
        $bundle = [];

        foreach ($formats as $format) {
            switch ($format) {
                case 'csv':
                    $bundle[$format] = $this->generateAmortizationScheduleAsCSV($loan, $schedule);
                    break;
                case 'excel':
                    $bundle[$format] = $this->generateAmortizationScheduleAsExcel($loan, $schedule);
                    break;
                case 'json':
                    $bundle[$format] = $this->exportToJSON($loan, $schedule);
                    break;
                case 'html':
                    $bundle[$format] = $this->exportToHTML($loan, $schedule);
                    break;
                case 'pdf':
                    $bundle[$format] = $this->exportToPDF($loan, $schedule);
                    break;
                default:
                    $bundle[$format] = '';
            }
        }

        return $bundle;
    }

    /**
     * Validate document for compliance
     */
    public function validateDocumentForCompliance(array $document): array
    {
        $issues = [];

        if (!isset($document['loan_id'])) {
            $issues[] = 'Missing loan_id';
        }

        if (!isset($document['principal']) || $document['principal'] <= 0) {
            $issues[] = 'Invalid principal';
        }

        if (!isset($document['rate'])) {
            $issues[] = 'Missing rate';
        }

        return [
            'compliant' => count($issues) === 0,
            'issues' => $issues,
            'validated_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Add document decorations (header, footer, watermark)
     */
    public function addDocumentDecorations(array $document, array $decorations): array
    {
        $document['decorations'] = $decorations;
        return $document;
    }

    /**
     * Export to PDF
     */
    public function exportToPDF(Loan $loan, array $schedule): string
    {
        // Simplified PDF generation - minimal valid PDF structure
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Length 100 >>\nstream\nBT\n";
        $pdf .= "/F1 12 Tf\n50 750 Td\n(Loan Amortization Schedule - Loan " . $loan->getId() . ") Tj\n";
        $pdf .= "ET\nendstream\nendobj\n";
        $pdf .= "xref\n0 5\n0000000000 65535 f\n";
        $pdf .= "0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n0000000217 00000 n\n";
        $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\n";
        $pdf .= "startxref\n365\n%%EOF\n";

        return $pdf;
    }
}
