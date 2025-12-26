<?php

global $path_to_root;

if( ! isset( $path_to_root ) )
{
	$path_to_root = __DIR__ . "/../../../../../";
}

class AdminSettings
{
    public static function render($selected = [])
    {
	global $path_to_root;
        require_once $path_to_root . '/gl/includes/db/gl_db_accounts.inc';

        // Get GL accounts by category
        $liability_gls = get_gl_accounts(CL_LIABILITIES);
        $asset_gls = get_gl_accounts(CL_ASSETS);
        $expense_gls = get_gl_accounts(CL_AMORTIZATION);
        $asset_value_gls = get_gl_accounts(CL_FIXEDASSETS);

        echo '<h2>Amortization Module - Admin Settings</h2>';
        echo '<form method="post">';
        self::gl_selector('liability_gl', $liability_gls, $selected['liability_gl'] ?? '');
        self::gl_selector('asset_gl', $asset_gls, $selected['asset_gl'] ?? '');
        self::gl_selector('expenses_gl', $expense_gls, $selected['expenses_gl'] ?? '');
        self::gl_selector('asset_value_gl', $asset_value_gls, $selected['asset_value_gl'] ?? '');
        echo '<button type="submit">Save Settings</button>';
        echo '</form>';
    }

    private static function gl_selector($name, $accounts, $selected = '')
    {
        echo "<label for='$name'>" . ucfirst(str_replace('_', ' ', $name)) . ":</label>";
        echo "<select name='$name' id='$name'>";
        foreach ($accounts as $acc) {
            $sel = ($acc['account_code'] == $selected) ? 'selected' : '';
            echo "<option value='{$acc['account_code']}' $sel>{$acc['account_name']}</option>";
        }
        echo "</select>";
    }
}

// Render the form
AdminSettings::render();
