<?php
namespace Ksfraser\Amortizations\Views;

class UserLoanSetupTable {
    public static function render(array $userLoans) {
        ob_start();
        ?>
        <h3>User Loan Setup</h3>
        <table border="1">
            <tr><th>ID</th><th>User</th><th>Loan Type</th><th>Amount</th><th>Actions</th></tr>
            <?php foreach ($userLoans as $loan): ?>
            <tr>
                <td><?= htmlspecialchars($loan->id) ?></td>
                <td><?= htmlspecialchars($loan->user) ?></td>
                <td><?= htmlspecialchars($loan->loan_type) ?></td>
                <td><?= htmlspecialchars($loan->amount) ?></td>
                <td>
                    <button>Edit</button>
                    <button>Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
        return ob_get_clean();
    }
}
