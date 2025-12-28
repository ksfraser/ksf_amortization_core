<?php
/**
 * Reporting View
 * Displays available reports
 * @package AmortizationModule
 */

use Ksfraser\Amortizations\Views\ReportingTable;
use Ksfraser\Amortizations\FA\FADataProvider;

// Note: This view is included by controller.php
// $db should be available from controller scope
global $db;

// Check if we're in a proper FA environment
if (!isset($db) || !$db) {
    echo '<p>Database connection not available. This view must be accessed through FrontAccounting.</p>';
    return;
}

// Use FADataProvider to get reports (follows Repository pattern)
// Exceptions propagate to FA's exception handler
$dataProvider = new FADataProvider($db);
$reports = $dataProvider->getAllReports();

// Use ReportingTable SRP class to render
echo ReportingTable::render($reports);
