<?php
/**
 * Controller for selector admin actions
 */
use Ksfraser\Amortizations\SelectorProvider;
// LoanType and InterestCalcFrequency are now autoloaded and used via SelectorProvider
use Ksfraser\Amortizations\Views\LoanTypeTable;
use Ksfraser\Amortizations\Views\InterestCalcFrequencyTable;

// Assume $db is available
$selectorProvider = new SelectorProvider($db);

// Handle add/edit/delete actions for loan types
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['loan_type_name'])) {
        $selectorProvider->addLoanType($_POST['loan_type_name'], $_POST['loan_type_desc'] ?? '');
    }
    if (!empty($_POST['interest_calc_freq_name'])) {
        $selectorProvider->addInterestCalcFrequency($_POST['interest_calc_freq_name'], $_POST['interest_calc_freq_desc'] ?? '');
    }
    // Add edit/delete logic as needed
}

$loanTypes = $selectorProvider->getLoanTypes();
$interestCalcFreqs = $selectorProvider->getInterestCalcFrequencies();

// Pass data to view classes
?>
<h2>Manage Selector Values</h2>
<?= LoanTypeTable::render($loanTypes) ?>
<?= InterestCalcFrequencyTable::render($interestCalcFreqs) ?>
