<?php

//print_r( __FILE__ . "::" . __LINE__, true );

//require_once __DIR__ . '/fa_mock.php'; // Remove in production
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/model.php';

use Ksfraser\Amortizations\AmortizationModel;



// Platform detection logic (simplified)
$platform = getenv('AMORTIZATION_PLATFORM') ?: 'fa';
global $db;
if ($platform === 'fa') {
    require_once __DIR__ . '/../fa/FADataProvider.php';
    $provider = new \Ksfraser\Amortizations\FA\FADataProvider($db);
} elseif ($platform === 'wordpress') {
    require_once __DIR__ . '/../wordpress/WPDataProvider.php';
    global $wpdb;
    $provider = new \Ksfraser\Amortizations\WordPress\WPDataProvider($wpdb);
} elseif ($platform === 'suitecrm') {
    require_once __DIR__ . '/../suitecrm/SuiteCRMDataProvider.php';
    $provider = new \Ksfraser\Amortizations\SuiteCRM\SuiteCRMDataProvider();
} else {
    throw new \Exception('Unknown platform for Amortization module');
}
$model = new AmortizationModel($provider);

// Handle loan creation/edit (admin & user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['amount_financed'])) {
        $data = [
            'loan_type' => $_POST['loan_type'],
            'description' => $_POST['description'],
            'amount_financed' => $_POST['amount_financed'],
            'interest_rate' => $_POST['interest_rate'],
            'payment_frequency' => $_POST['payment_frequency'],
            'interest_calc_frequency' => $_POST['interest_calc_frequency'],
            'loan_term_years' => $_POST['loan_term_years'],
            'payments_per_year' => $_POST['payments_per_year'],
            'regular_payment' => $_POST['regular_payment'],
            'override_payment' => isset($_POST['override_payment']) ? 1 : 0,
            'first_payment_date' => $_POST['first_payment_date'],
            'last_payment_date' => $_POST['last_payment_date'],
            'created_by' => 1, // Replace with logged-in user
            // legacy fields for compatibility
            'principal' => $_POST['amount_financed'],
            // 'term_months' replaced by loan_term_years and payments_per_year
            'repayment_schedule' => $_POST['payment_frequency'],
            'start_date' => $_POST['first_payment_date'],
            'end_date' => $_POST['last_payment_date']
        ];
        if (!empty($_POST['edit_loan_id'])) {
            // Edit logic: update all fields for the loan
            $loanId = $_POST['edit_loan_id'];
            // Calculate payment if not overridden
            if (!$data['override_payment']) {
                $num_payments = (int)$data['loan_term_years'] * (int)$data['payments_per_year'];
                $data['regular_payment'] = round($model->calculatePayment($data['amount_financed'], $data['interest_rate'], $num_payments), 2);
            }
            $model->updateLoan($loanId, $data);
        } else {
            // Calculate payment if not overridden
            if (!$data['override_payment']) {
                $num_payments = (int)$data['loan_term_years'] * (int)$data['payments_per_year'];
                $data['regular_payment'] = round($model->calculatePayment($data['amount_financed'], $data['interest_rate'], $num_payments), 2);
            }
            $model->createLoan($data);
        }
    }
}

// Get loans for display

$loans = [];
/*
try {
    // Use provider abstraction
    $stmt = $db->prepare('SELECT * FROM fa_loans');
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist in dev
}
*/

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'create':
        // Pass loans to user_loan_setup.php for selection
        include __DIR__ . '/user_loan_setup.php';
        break;
    case 'admin':
        // Pass loans to admin_settings.php for management
        include __DIR__ . '/admin_settings.php';
        break;
    case 'report':
        include __DIR__ . '/reporting.php';
        break;
    default:
        // List loans and allow selection
        include __DIR__ . '/view.php';
        break;
}
