<?php
namespace Ksfraser\Amortizations\Views;

use Ksfraser\HTML\Elements\Heading;
use Ksfraser\HTML\Elements\Table;
use Ksfraser\HTML\Elements\TableRow;
use Ksfraser\HTML\Elements\TableData;
use Ksfraser\HTML\Elements\TableHeader;
use Ksfraser\HTML\Elements\Button;
use Ksfraser\HTML\Elements\Div;
use Ksfraser\HTML\Elements\HtmlParagraph;
use Ksfraser\HTML\Elements\HtmlString;
use Ksfraser\HTML\Elements\CreateActionLink;
use Ksfraser\HTML\Elements\ModalBuilder;
use Ksfraser\HTML\ScriptHandlers\ReportScriptHandler;
use Ksfraser\Amortizations\Views\StylesheetManager;

/**
 * ReportingTable - Displays available reports
 * 
 * Provides table view of generated and available reports with view action.
 * Uses HTML builder pattern for clean, maintainable code.
 * SRP: Single responsibility of reporting table presentation.
 * 
 * @package Ksfraser\Amortizations\Views
 */
class ReportingTable {
    /**
     * Render reports table
     * 
     * @param array $reports Array of report objects
     * @return string HTML rendering of the table
     */
    public static function render(array $reports = []): string {
        $output = '';
        
        // Load stylesheets
        $output .= self::getStylesheets();
        
        // Build heading
        $heading = (new Heading(3))->setText('Reports');
        $output .= $heading->render();
        
        // Check if no reports
        if (empty($reports)) {
            $emptyMsg1 = new HtmlParagraph(new HtmlString(
                'No reports available. Reports are generated when you create and calculate loan amortization schedules.'
            ));
            $output .= $emptyMsg1->getHtml();
            
            $createLink = new CreateActionLink('Create a loan');
            $emptyMsg2 = new HtmlParagraph(new HtmlString(
                $createLink->getHtml() . ' to generate your first report.'
            ));
            $output .= $emptyMsg2->getHtml();
            return $output;
        }
        
        // Build table
        $table = (new Table())->addClass('reporting-table');
        
        // Header row
        $headerRow = (new TableRow())->addClass('header-row');
        $headerRow->append(
            (new TableHeader())->setText('ID'),
            (new TableHeader())->setText('Type'),
            (new TableHeader())->setText('Date'),
            (new TableHeader())->setText('Actions')
        );
        $table->append($headerRow);
        
        // Data rows
        foreach ($reports as $report) {
            $row = (new TableRow())->addClass('data-row');
            
            $row->append((new TableData())
                ->addClass('id-cell')
                ->setText((string)($report->id ?? 'N/A'))
            );
            
            $row->append((new TableData())
                ->addClass('type-cell')
                ->setText(htmlspecialchars($report->type ?? 'Unknown'))
            );
            
            // Format date if provided
            $dateText = 'N/A';
            if (isset($report->date)) {
                try {
                    $dateObj = is_string($report->date) 
                        ? new \DateTime($report->date)
                        : $report->date;
                    $dateText = $dateObj->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $dateText = htmlspecialchars($report->date);
                }
            }
            $row->append((new TableData())
                ->addClass('date-cell')
                ->setText($dateText)
            );
            
            // Actions
            $actionsCell = (new TableData())->addClass('actions-cell');
            $actionsDiv = (new Div())->addClass('action-buttons');
            
            $viewBtn = (new Button())
                ->setType('button')
                ->addClass('btn-small btn-view')
                ->setText('View')
                ->setAttribute('onclick', 'window.viewReport ? viewReport(' . intval($report->id ?? 0) . ') : console.log("Handler not loaded")');
            $actionsDiv->append($viewBtn);
            
            // Add download button if available
            if (isset($report->download_url)) {
                $downloadBtn = (new Button())
                    ->setType('button')
                    ->addClass('btn-small btn-download')
                    ->setText('Download')
                    ->setAttribute('onclick', 'window.location.href = "' . htmlspecialchars($report->download_url) . '"');
                $actionsDiv->append($downloadBtn);
            }
            
            $actionsCell->append($actionsDiv);
            $row->append($actionsCell);
            $table->append($row);
        }
        
        $output .= $table->render();
        
        // Add JavaScript handlers via SRP class
        $scriptHandler = new ReportScriptHandler();
        $output .= $scriptHandler->render();
        
        return $output;
    }
    
    /**
     * Get stylesheets for this view
     */
    private static function getStylesheets(): string {
        $modalStylesheet = ModalBuilder::getStylesheet();
        return $modalStylesheet->getHtml() . StylesheetManager::getStylesheets('reporting');
    }
}
