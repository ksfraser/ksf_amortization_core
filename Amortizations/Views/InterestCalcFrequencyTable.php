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
use Ksfraser\HTML\ScriptHandlers\InterestFreqScriptHandler;
use Ksfraser\HTML\Rows\InterestFreqTableRow;
use Ksfraser\Amortizations\Views\StylesheetManager;

/**
 * InterestCalcFrequencyTable - Displays and manages interest calculation frequencies
 * 
 * Provides table view of all interest calculation frequencies with add/edit/delete.
 * Uses HTML builder pattern for clean, maintainable code.
 * SRP: Single responsibility of interest frequency table presentation.
 * 
 * @package Ksfraser\Amortizations\Views
 */
class InterestCalcFrequencyTable {
    /**
     * Render interest calculation frequencies table
     * 
     * @param array $interestCalcFreqs Array of frequency objects
     * @return string HTML rendering of the table and form
     */
    public static function render(array $interestCalcFreqs = []): string {
        $output = '';
        
        // Load stylesheets
        $output .= self::getStylesheets();
        
        // Build heading
        $heading = (new Heading(3))->setText('Interest Calculation Frequencies');
        $output .= $heading->render();
        
        // Build table
        $table = (new Table())->addClass('interest-freq-table');
        
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
        $rowBuilder = new InterestFreqTableRow();
        foreach ($interestCalcFreqs as $freq) {
            $table->append($rowBuilder->build($freq));
        }
        
        $output .= $table->render();
        
        // Build add form
        $form = (new Form())
            ->setMethod('POST')
            ->addClass('add-interest-freq-form');
        
        $formContainer = (new Div())->addClass('form-container');
        
        $nameGroup = (new Div())->addClass('form-group');
        $nameGroup->append((new Input())
            ->setType('text')
            ->setName('interest_calc_freq_name')
            ->setAttribute('placeholder', 'New Frequency')
            ->setRequired(true)
        );
        $formContainer->append($nameGroup);
        
        $descGroup = (new Div())->addClass('form-group');
        $descGroup->append((new Input())
            ->setType('text')
            ->setName('interest_calc_freq_desc')
            ->setAttribute('placeholder', 'Description')
            ->setRequired(true)
        );
        $formContainer->append($descGroup);
        
        $submitBtn = (new Button())
            ->setType('submit')
            ->addClass('btn btn-primary')
            ->setText('Add Frequency');
        $formContainer->append($submitBtn);
        
        $form->append($formContainer);
        $output .= $form->render();
        
        // Add handler scripts
        $scriptHandler = new InterestFreqScriptHandler();
        $output .= $scriptHandler->render();
        
        return $output;
    }
    
    /**
     * Get stylesheets for this view
     */
    private static function getStylesheets(): string {
        return StylesheetManager::getStylesheets('interest-freq');
    }
}

