<?php
/**
 * Admin screen for managing selector values (loan types, interest calc frequencies)
 */
use Ksfraser\Amortizations\SelectorProvider;
use Ksfraser\Amortizations\Views\LoanTypeTable;
use Ksfraser\Amortizations\Views\InterestCalcFrequencyTable;

// Assume $db is available
$selectorProvider = new SelectorProvider($db);
$loanTypes = $selectorProvider->getLoanTypes();
$interestCalcFreqs = $selectorProvider->getInterestCalcFrequencies();

// Handle add/edit/delete actions (pseudo-code, implement as needed)
// ...existing code...
?>
<h2>Manage Selector Values</h2>
<?= LoanTypeTable::render($loanTypes) ?>
<?= InterestCalcFrequencyTable::render($interestCalcFreqs) ?>
