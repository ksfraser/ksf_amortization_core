<?php
namespace Ksfraser\Amortizations\Views;

use Ksfraser\HTML\Elements\Heading;
use Ksfraser\HTML\Elements\Table;
use Ksfraser\HTML\Elements\TableRow;
use Ksfraser\HTML\Elements\TableData;
use Ksfraser\HTML\Elements\Button;
use Ksfraser\HTML\Elements\Div;
use Ksfraser\HTML\Elements\HtmlParagraph;
use Ksfraser\HTML\Elements\HtmlString;
use Ksfraser\HTML\Elements\CreateActionLink;
use Ksfraser\HTML\ScriptHandlers\LoanScriptHandler;
use Ksfraser\HTML\Rows\LoanSummaryTableRow;
use Ksfraser\Amortizations\Views\StylesheetManager;

/**
 * LoanSummaryTable - Displays loan summary information
 * 
 * Provides table view of all loans with ID, borrower, amount, and status.
 * Includes view and edit actions for each loan.
 * Uses HTML builder pattern for clean, maintainable code.
 * SRP: Single responsibility of loan summary table presentation.
 * 
 * @package Ksfraser\Amortizations\Views
 */
class LoanSummaryTable {
    /**
     * Render loan summary table
     * 
     * @param array $loans Array of loan objects
     * @return string HTML rendering of the table
     */
    public static function render(array $loans = []): string {
        $output = '';
        
        // Load stylesheets
        $output .= self::getStylesheets();
        
        // Build heading
        $heading = (new Heading(3))->setText('Loan Summary');
        $output .= $heading->render();
        
        // Check if no loans
        if (empty($loans)) {
            $createLink = new CreateActionLink('Create your first loan');
            $emptyMsg = new HtmlParagraph(new HtmlString(
                'No loans found. ' . $createLink->getHtml()
            ));
            $output .= $emptyMsg->getHtml();
            return $output;
        }
        
        // Build table
        $table = (new Table())->addClass('loan-summary-table');
        
        // Header row
        $headerRow = (new TableRow())->addClass('header-row');
        $headerRow->addHeadersFromArray(['ID', 'Borrower', 'Amount', 'Status', 'Actions']);
        $table->append($headerRow);
        
        // Data rows
        $rowBuilder = new LoanSummaryTableRow();
        foreach ($loans as $loan) {
            $table->append($rowBuilder->build($loan));
        }
        
        $output .= $table->render();
        
        // Add handler scripts
        $scriptHandler = new LoanScriptHandler();
        $output .= $scriptHandler->render();
        
        return $output;
    }
    
    /**
     * Get stylesheets for this view
     */
    private static function getStylesheets(): string {
        return StylesheetManager::getStylesheets('loan-summary');
    }
}

