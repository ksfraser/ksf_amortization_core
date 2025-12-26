<?php
namespace Ksfraser\Amortizations\Views;

class AdminSettingsTable {
    public static function render(array $settings) {
        ob_start();
        ?>
        <h3>Admin Settings</h3>
        <table border="1">
            <tr><th>Setting</th><th>Value</th><th>Actions</th></tr>
            <?php foreach ($settings as $setting): ?>
            <tr>
                <td><?= htmlspecialchars($setting->name) ?></td>
                <td><?= htmlspecialchars($setting->value) ?></td>
                <td>
                    <button>Edit</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
        return ob_get_clean();
    }
}
