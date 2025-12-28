<?php
namespace Ksfraser\Amortizations\Views;

use Ksfraser\HTML\AbstractStylesheetManager;

/**
 * StylesheetManager - Amortization module stylesheet configuration
 * 
 * Extends AbstractStylesheetManager (from HTML package) with amortization-specific
 * view name mappings to CSS files.
 * 
 * Maps view names to their specific stylesheets:
 * - Common stylesheets loaded once per request (cached)
 * - View-specific stylesheets loaded per view
 * - Supports platform-specific skinning via asset_url()
 * 
 * Common stylesheets (shared):
 * - common.css: Reusable button/form/table base styles
 * - tables-base.css: Generic table structure and layout
 * - status-badges.css: Status color patterns (active, pending, completed, etc)
 * - forms-base.css: Form container and input base styles
 * - buttons-base.css: Button variants and states
 * 
 * @package Ksfraser\Amortizations\Views
 */
class StylesheetManager extends AbstractStylesheetManager {
    /**
     * Common stylesheet names (shared across all amortization views)
     * 
     * These are loaded once and cached, improving performance when
     * multiple amortization components appear on the same page.
     * 
     * @var array<string>
     */
    protected static array $commonSheets = [
        'common',           // Reusable button/form/table styles
        'tables-base',      // Generic table structure
        'status-badges',    // Status color patterns
        'forms-base',       // Form container base
        'buttons-base',     // Button variants
    ];
    
    /**
     * View-specific stylesheet names (unique per amortization view)
     * 
     * Maps view identifiers to their view-specific stylesheet files.
     * These contain only styles unique to that view, with common
     * styles provided by $commonSheets.
     * 
     * @var array<string, array<string>>
     */
    protected static array $viewSheets = [
        'loan-types' => ['loan-types'],
        'loan-summary' => ['loan-summary'],
        'interest-freq' => ['interest-freq'],
        'reporting' => ['reporting'],
    ];
}
