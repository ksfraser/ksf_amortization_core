<?php

namespace Ksfraser\Amortizations\Reports;

use Ksfraser\Amortizations\Models\Loan;

/**
 * ScenarioPdfReportGenerator - Generate PDF Reports for Scenarios
 *
 * Generates professional PDF reports for scenario analysis and what-if modeling.
 * Uses HTML content and converts to PDF format for distribution and archival.
 *
 * Features:
 * - Single scenario PDF reports
 * - Scenario comparison PDF reports
 * - Professional formatting with headers/footers
 * - Page breaks for large schedules
 * - Watermarks for draft/final versions
 * - Chart generation (optional with Chart.js)
 *
 * Note: Actual PDF generation requires external library (e.g., DomPDF, wkhtmltopdf).
 * This class provides the PDF structure and content generation.
 *
 * @package    Ksfraser\Amortizations\Reports
 * @author     Development Team
 * @since      Phase 10
 * @version    1.0.0
 */
class ScenarioPdfReportGenerator
{
    /**
     * @var ScenarioReportGenerator
     */
    private $htmlReportGenerator;

    /**
     * @var string PDF library to use (dompdf, mpdf, tcpdf)
     */
    private $pdfLibrary = 'dompdf';

    public function __construct(ScenarioReportGenerator $htmlReportGenerator)
    {
        $this->htmlReportGenerator = $htmlReportGenerator;
    }

    /**
     * Generate PDF for single scenario
     *
     * @param array $scenario
     * @param array $schedule
     * @param Loan $loan
     * @param string $filename Output filename
     * @return string PDF content or filename
     */
    public function generateScenarioPdf(
        array $scenario,
        array $schedule,
        Loan $loan,
        string $filename = ''
    ): string {
        // Generate HTML content
        $htmlContent = $this->htmlReportGenerator->generateScenarioHtmlReport($scenario, $schedule, $loan);

        // Wrap in PDF-formatted HTML
        $pdfHtml = $this->generatePdfWrapper(
            $htmlContent,
            'Loan Scenario Analysis Report',
            $scenario['name'],
            'final'
        );

        // Convert to PDF (implementation depends on chosen library)
        return $this->htmlToPdf($pdfHtml, $filename);
    }

    /**
     * Generate PDF for scenario comparison
     *
     * @param array $scenario1
     * @param array $schedule1
     * @param array $scenario2
     * @param array $schedule2
     * @param Loan $loan
     * @param string $filename
     * @return string PDF content or filename
     */
    public function generateComparisonPdf(
        array $scenario1,
        array $schedule1,
        array $scenario2,
        array $schedule2,
        Loan $loan,
        string $filename = ''
    ): string {
        // Generate HTML content
        $htmlContent = $this->htmlReportGenerator->generateComparisonHtmlReport(
            $scenario1,
            $schedule1,
            $scenario2,
            $schedule2,
            $loan
        );

        // Wrap in PDF-formatted HTML
        $pdfHtml = $this->generatePdfWrapper(
            $htmlContent,
            'Loan Scenario Comparison Report',
            $scenario1['name'] . ' vs ' . $scenario2['name'],
            'final'
        );

        return $this->htmlToPdf($pdfHtml, $filename);
    }

    /**
     * Generate PDF wrapper with headers, footers, styling
     *
     * @param string $content HTML content
     * @param string $title Report title
     * @param string $subtitle Report subtitle
     * @param string $watermark Watermark text (draft, final, confidential)
     * @return string Complete PDF HTML
     */
    private function generatePdfWrapper(
        string $content,
        string $title,
        string $subtitle,
        string $watermark = 'final'
    ): string {
        $watermarkClass = '';
        if ($watermark === 'draft') {
            $watermarkClass = 'watermark-draft';
        } elseif ($watermark === 'confidential') {
            $watermarkClass = 'watermark-confidential';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$title} - {$subtitle}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
        }

        .pdf-header {
            border-bottom: 2px solid #1976d2;
            padding-bottom: 12px;
            margin-bottom: 20px;
            page-break-after: avoid;
        }

        .pdf-header h1 {
            font-size: 18pt;
            color: #1976d2;
            margin-bottom: 4px;
        }

        .pdf-header h2 {
            font-size: 14pt;
            color: #666;
            font-weight: normal;
        }

        .pdf-header .metadata {
            font-size: 10pt;
            color: #999;
            margin-top: 8px;
        }

        .pdf-footer {
            text-align: center;
            font-size: 9pt;
            color: #999;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            page-break-before: avoid;
        }

        .content {
            margin: 20px 0;
        }

        h2 {
            font-size: 14pt;
            color: #1976d2;
            margin-top: 20px;
            margin-bottom: 12px;
            page-break-after: avoid;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 8px;
        }

        h3 {
            font-size: 12pt;
            color: #333;
            margin-top: 16px;
            margin-bottom: 10px;
            page-break-after: avoid;
        }

        p {
            margin-bottom: 10px;
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            page-break-inside: avoid;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }

        table th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #1976d2;
        }

        table td:first-child,
        table th:first-child {
            text-align: left;
        }

        .summary-table,
        .comparison-table {
            width: 100%;
            margin: 12px 0;
        }

        .summary-table td,
        .comparison-table td {
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 6px 4px;
        }

        .summary-table td:first-child,
        .comparison-table td:first-child {
            width: 40%;
            font-weight: bold;
        }

        .savings-positive {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px;
            margin: 12px 0;
        }

        .savings-negative {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 12px;
            margin: 12px 0;
        }

        .savings-neutral {
            background-color: #f5f5f5;
            border-left: 4px solid #999;
            padding: 12px;
            margin: 12px 0;
        }

        .key-metrics {
            background-color: #f0f7ff;
            border: 1px solid #90caf9;
            padding: 12px;
            margin: 12px 0;
            page-break-inside: avoid;
        }

        ul {
            margin-left: 20px;
            margin-bottom: 10px;
        }

        li {
            margin-bottom: 6px;
        }

        .page-break {
            page-break-after: always;
        }

        /* Watermarks */
        .watermark-draft::before {
            content: 'DRAFT';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60pt;
            color: rgba(0, 0, 0, 0.1);
            font-weight: bold;
            z-index: -1;
            width: 100%;
            text-align: center;
        }

        .watermark-confidential::before {
            content: 'CONFIDENTIAL';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60pt;
            color: rgba(255, 0, 0, 0.1);
            font-weight: bold;
            z-index: -1;
            width: 100%;
            text-align: center;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .pdf-header {
                position: running(header);
            }
            .pdf-footer {
                position: running(footer);
            }
        }
    </style>
</head>
<body class="{$watermarkClass}">
    <div class="pdf-header">
        <h1>{$title}</h1>
        <h2>{$subtitle}</h2>
        <div class="metadata">
            Generated: {$this->getFormattedDate()} | Status: {$watermark}
        </div>
    </div>

    <div class="content">
        {$content}
    </div>

    <div class="pdf-footer">
        <p>This report is generated automatically by the Amortization Module.</p>
        <p>For questions, contact your loan administrator.</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Convert HTML to PDF
     *
     * This is a placeholder that should be implemented with actual PDF library.
     * Options include:
     * - DomPDF: https://github.com/barryvdh/laravel-dompdf
     * - mPDF: https://mpdf.github.io/
     * - TCPDF: https://tcpdf.org/
     *
     * @param string $html HTML content
     * @param string $filename Output filename
     * @return string PDF content
     * @throws \Exception
     */
    private function htmlToPdf(string $html, string $filename = ''): string
    {
        $filename = $filename ?: 'scenario-report-' . date('Y-m-d-His') . '.pdf';

        // Check if DomPDF is available
        if (!class_exists('Dompdf\Dompdf')) {
            // Fallback: return HTML if DomPDF not installed
            error_log('DomPDF not available - returning HTML instead of PDF');
            return $html;
        }

        try {
            // Initialize DomPDF
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            $dompdf = new \Dompdf\Dompdf($options);
            
            // Load HTML content
            $dompdf->loadHtml($html);
            
            // Set paper size and orientation
            $dompdf->setPaper('A4', 'portrait');
            
            // Render PDF (first pass to calculate pages)
            $dompdf->render();
            
            // Return PDF output as string
            return $dompdf->output();
            
        } catch (\Exception $e) {
            error_log('PDF generation error: ' . $e->getMessage());
            // Fallback to HTML on error
            return $html;
        }
    }

    /**
     * Get formatted current date
     *
     * @return string
     */
    private function getFormattedDate(): string
    {
        return date('F j, Y \a\t g:i A');
    }

    /**
     * Set PDF library to use
     *
     * @param string $library Library name (dompdf, mpdf, tcpdf)
     * @return self Fluent interface
     */
    public function setPdfLibrary(string $library): self
    {
        $this->pdfLibrary = $library;
        return $this;
    }
}
