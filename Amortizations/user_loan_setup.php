<?php
/**
 * User Loan Selection and Setup View
 * @package AmortizationModule
 */
?>
<h2>Select Loan to Review</h2>
<form method="get" action="">
    <label for="loan_id">Loan:</label>
    <select name="loan_id" id="loan_id">
        <!-- Populate with loans -->
    </select>
    <input type="submit" value="Review Loan">
</form>
<hr>
<h2>Setup New Loan</h2>
<form method="post" action="">
    <label for="loan_type">Loan Type:</label>
    <select name="loan_type" id="loan_type">
        <option value="Auto">Auto</option>
        <option value="Mortgage">Mortgage</option>
        <option value="Other">Other</option>
    </select><br><br>
    <label for="interest_calc_frequency">Interest Calculation Frequency:</label>
    <select name="interest_calc_frequency" id="interest_calc_frequency">
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="bi-weekly">Bi-Weekly</option>
        <option value="semi-monthly">Semi-Monthly</option>
        <option value="monthly">Monthly</option>
        <option value="semi-annual">Semi-Annual</option>
        <option value="annual">Annual</option>
    </select><br><br>
    <label for="description">Description:</label>
    <input type="text" name="description" id="description"><br><br>
    <label for="amount_financed">Amount Financed:</label>
    <input type="number" name="amount_financed" id="amount_financed" step="0.01"><br><br>
    <label for="interest_rate">Interest Rate (%):</label>
    <input type="number" name="interest_rate" id="interest_rate" step="0.01"><br><br>
    <label for="payment_frequency">Payment Frequency:</label>
    <select name="payment_frequency" id="payment_frequency">
        <option value="monthly">Monthly</option>
        <option value="bi-weekly">Bi-Weekly</option>
        <option value="weekly">Weekly</option>
        <option value="custom">Custom</option>
    </select><br><br>
    <label for="interest_calc_frequency">Interest Calculation Frequency:</label>
    <select name="interest_calc_frequency" id="interest_calc_frequency">
        <option value="monthly">Monthly</option>
        <option value="bi-weekly">Bi-Weekly</option>
        <option value="weekly">Weekly</option>
        <option value="custom">Custom</option>
    </select><br><br>
    <label for="num_payments">Number of Payments:</label>
    <input type="number" name="num_payments" id="num_payments"><br><br>
    <label for="regular_payment">Regular Payment Amount:</label>
    <input type="number" name="regular_payment" id="regular_payment" step="0.01" readonly><br><br>
    <label for="first_payment_date">First Payment Date:</label>
    <input type="date" name="first_payment_date" id="first_payment_date"><br><br>
    <label for="last_payment_date">Last Payment Date:</label>
    <input type="date" name="last_payment_date" id="last_payment_date"><br><br>
    <input type="submit" value="Create Loan">
</form>
