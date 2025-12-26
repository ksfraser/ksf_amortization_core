<?php
namespace Ksfraser\Amortizations\Views;

use Ksfraser\HTML\Elements\Heading;
use Ksfraser\HTML\Elements\Table;
use Ksfraser\HTML\Elements\TableRow;
use Ksfraser\HTML\Elements\TableData;
use Ksfraser\HTML\Elements\TableHeader;
use Ksfraser\HTML\Elements\Form;
use Ksfraser\HTML\Elements\Input;
use Ksfraser\HTML\Elements\Button;
use Ksfraser\HTML\Elements\Div;
use Ksfraser\HTML\ScriptHandlers\LoanTypeScriptHandler;
use Ksfraser\HTML\Rows\LoanTypeTableRow;
use Ksfraser\Amortizations\Views\StylesheetManager;

/**
 * LoanTypeTable - Displays and manages loan types
 * 
 * Provides table view of all loan types with add/edit/delete functionality.
 * Uses HTML builder pattern for clean, maintainable code.
 * SRP: Single responsibility of loan type table presentation.
 * 
 * @package Ksfraser\Amortizations\Views
 */
class LoanTypeTable {
    /**
     * Render loan types table with management interface
     * 
     * @param array $loanTypes Array of loan type objects
     * @return string HTML rendering of the table and form
     */
    public static function render(array $loanTypes = []): string {
        $output = '';
        
        // Load external CSS files
        $output .= self::getStylesheets();
        
        // Build heading
        $heading = (new Heading(3))->setText('Loan Types');
        $output .= $heading->render();
        
        // Build table
        $table = (new Table())->addClass('loan-types-table');
        
        // Header row
        $headerRow = (new TableRow())->addClass('header-row');
        $headerRow->append(
            (new TableHeader())->setText('ID'),
            (new TableHeader())->setText('Name'),
            (new TableHeader())->setText('Description'),
            (new TableHeader())->setText('Actions')
        );
        $table->append($headerRow);
        
        // Data rows
        $rowBuilder = new LoanTypeTableRow();
        foreach ($loanTypes as $type) {
            $table->append($rowBuilder->build($type));
        }
        
        $output .= $table->render();
        
        // Build add form
        $form = (new Form())
            ->setMethod('POST')
            ->addClass('add-loan-type-form');
        
        $formContainer = (new Div())->addClass('form-container');
        
        // Loan type name input
        $nameGroup = (new Div())->addClass('form-group');
        $nameGroup->append((new Input())
            ->setType('text')
            ->setName('loan_type_name')
            ->setAttribute('placeholder', 'New Loan Type')
            ->setRequired(true)
        );
        $formContainer->append($nameGroup);
        
        // Description input
        $descGroup = (new Div())->addClass('form-group');
        $descGroup->append((new Input())
            ->setType('text')
            ->setName('loan_type_desc')
            ->setAttribute('placeholder', 'Description')
            ->setRequired(true)
        );
        $formContainer->append($descGroup);
        
        // Submit button
        $submitBtn = (new Button())
            ->setType('submit')
            ->addClass('btn btn-primary')
            ->setText('Add Loan Type');
        $formContainer->append($submitBtn);
        
        $form->append($formContainer);
        $output .= $form->render();
        
        // Add handler scripts
        $scriptHandler = new LoanTypeScriptHandler();
        $output .= $scriptHandler->render();
        
        return $output;
    }
    
    /**
     * Get stylesheets for this view
     */
    private static function getStylesheets(): string {
        return StylesheetManager::getStylesheets('loan-types');
    }
}

