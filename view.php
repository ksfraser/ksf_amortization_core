<?php
/**
 * Main View - Loan List Display
 * Lists loans and provides navigation
 * @package AmortizationModule
 */

use Ksfraser\Amortizations\Views\LoanSummaryTable;
use Ksfraser\Amortizations\FA\FADataProvider;

// Note: This view is included by controller.php
// $db should be available from controller scope
global $db;

// Check if we're in a proper FA environment
if (!isset($db) || !$db) {
    echo '<p>Database connection not available. This view must be accessed through FrontAccounting.</p>';
    return;
}

// Use FADataProvider to get loans (follows Repository pattern)
// Exceptions propagate to FA's exception handler
$dataProvider = new FADataProvider($db);
$loans = $dataProvider->getAllLoans();

// Use LoanSummaryTable SRP class to render
echo LoanSummaryTable::render($loans);
