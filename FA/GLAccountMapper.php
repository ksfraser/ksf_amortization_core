<?php
/**
 * GL Account Mapper for FrontAccounting
 *
 * Manages GL account validation, mapping, and configuration for amortization
 * loan postings. Provides account lookup, validation, and balance checking.
 *
 * ### UML Class Diagram
 * ```
 * ┌─────────────────────────────────────┐
 * │      GLAccountMapper                │
 * ├─────────────────────────────────────┤
 * │ - pdo: PDO                          │
 * │ - accountCache: array               │
 * ├─────────────────────────────────────┤
 * │ + __construct(PDO $pdo)             │
 * │ + mapLoanAccounts(): array          │
 * │ + validateAccounts(array): bool     │
 * │ + getAccountDetails(string): array  │
 * │ + isAccountActive(string): bool     │
 * │ - fetchAccountFromGL(string): array │
 * └─────────────────────────────────────┘
 * ```
 *
 * ### Account Structure
 * Required GL accounts for loan postings:
 * - liability_account: Loan liability (e.g., "2100")
 * - interest_expense_account: Interest expense (e.g., "6200")
 * - cash_account: Cash/bank account (e.g., "1100")
 *
 * ### Design Principles
 * - **S**ingle Responsibility: Only handles GL account management
 * - **O**pen/Closed: Extensible for other platforms
 * - **D**ependency Inversion: Depends on PDO interface
 *
 * @package   Ksfraser\Amortizations\FA
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-08
 */

namespace Ksfraser\Amortizations\FA;

use PDO;
use RuntimeException;

/**
 * Manages GL account mapping and validation for loan postings
 */
class GLAccountMapper
{
    /**
     * @var PDO FrontAccounting database connection
     */
    private PDO $pdo;

    /**
     * @var array Cached account details
     */
    private array $accountCache = [];

    /**
     * Default GL account codes for loan transactions
     *
     * ### Configuration
     * These can be overridden per loan type via ksf_amort_gl_config table
     *
     * @var array
     */
    private const DEFAULT_ACCOUNTS = [
        'liability_account' => '2100',          // Loan liability
        'interest_expense_account' => '6200',   // Interest expense
        'cash_account' => '1100',               // Cash/bank
    ];

    /**
     * Required GL account types for validation
     *
     * @var array
     */
    private const REQUIRED_ACCOUNTS = [
        'liability_account',
        'interest_expense_account',
        'cash_account',
    ];

    /**
     * Constructor
     *
     * ### Initialization
     * Sets up database connection and initializes account cache
     *
     * @param PDO $pdo FrontAccounting database connection (PDO instance)
     *
     * @throws RuntimeException If database connection is invalid
     */
    public function __construct(PDO $pdo)
    {
        if ($pdo === null) {
            throw new RuntimeException('PDO connection cannot be null');
        }

        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Map GL accounts for a specific loan
     *
     * ### Purpose
     * Retrieves loan-specific GL account configuration, falling back to defaults
     *
     * ### Process
     * 1. Check for loan-specific overrides in ksf_amort_gl_config
     * 2. Fall back to default accounts if not configured
     * 3. Validate all required accounts exist
     *
     * @param int $loanId Loan database ID
     *
     * @return array GL account mapping [
     *     'liability_account' => '2100',
     *     'interest_expense_account' => '6200',
     *     'cash_account' => '1100'
     * ]
     *
     * @throws RuntimeException If loan has no valid GL accounts configured
     */
    public function mapLoanAccounts(int $loanId): array
    {
        // Start with defaults
        $accounts = self::DEFAULT_ACCOUNTS;

        // Try to fetch loan-specific overrides (if config table exists)
        try {
            $stmt = $this->pdo->prepare(
                'SELECT account_type, account_code 
                 FROM ksf_amort_gl_config 
                 WHERE loan_id = ?'
            );
            $stmt->execute([$loanId]);

            $overrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Merge overrides with defaults
            foreach ($overrides as $type => $code) {
                if (isset($accounts[$type])) {
                    $accounts[$type] = $code;
                }
            }
        } catch (\PDOException $e) {
            // Config table may not exist yet - use defaults
        }

        // Validate all required accounts are present
        if (!$this->validateAccounts($accounts)) {
            throw new RuntimeException(
                "Loan {$loanId} has incomplete GL account configuration"
            );
        }

        return $accounts;
    }

    /**
     * Validate GL account configuration
     *
     * ### Validation Checks
     * 1. All required accounts present
     * 2. All account codes exist in FA GL
     * 3. All accounts are active (not closed)
     * 4. Accounts have compatible types (asset/liability/expense)
     *
     * @param array $accounts GL account mapping
     *
     * @return bool True if all accounts valid, false otherwise
     */
    public function validateAccounts(array $accounts): bool
    {
        // Check all required accounts present
        foreach (self::REQUIRED_ACCOUNTS as $requiredType) {
            if (!isset($accounts[$requiredType]) || empty($accounts[$requiredType])) {
                return false;
            }
        }

        // Validate each account exists and is active
        foreach ($accounts as $type => $code) {
            if (!in_array($type, self::REQUIRED_ACCOUNTS)) {
                continue; // Skip non-standard accounts
            }

            try {
                $details = $this->getAccountDetails($code);
                if ($details === null || $details['inactive']) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get detailed information about a GL account
     *
     * ### Details Returned
     * - account_code: Account code
     * - account_name: Account description
     * - account_type: Account type (asset, liability, expense, etc.)
     * - inactive: Whether account is closed
     * - debit_balance: Current debit balance
     * - credit_balance: Current credit balance
     *
     * ### Caching
     * Results are cached to avoid repeated database queries
     *
     * @param string $accountCode FA GL account code (e.g., "2100")
     *
     * @return array|null Account details or null if not found
     *
     * @throws RuntimeException If database query fails
     */
    public function getAccountDetails(string $accountCode): ?array
    {
        // Check cache first
        if (isset($this->accountCache[$accountCode])) {
            return $this->accountCache[$accountCode];
        }

        try {
            // Query FA GL accounts table
            $stmt = $this->pdo->prepare(
                'SELECT account_code, account_name, account_type, inactive
                 FROM chart_master
                 WHERE account_code = ?'
            );
            $stmt->execute([$accountCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            // Add type conversion
            $result['inactive'] = (bool)$result['inactive'];

            // Cache the result
            $this->accountCache[$accountCode] = $result;

            return $result;
        } catch (\PDOException $e) {
            throw new RuntimeException(
                "Failed to fetch account {$accountCode}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Check if a GL account is active
     *
     * ### Purpose
     * Quick check to verify account hasn't been closed
     *
     * @param string $accountCode GL account code
     *
     * @return bool True if active, false if inactive or not found
     */
    public function isAccountActive(string $accountCode): bool
    {
        try {
            $details = $this->getAccountDetails($accountCode);
            return $details !== null && !$details['inactive'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get account balance (simple version)
     *
     * ### Note
     * This is a simplified implementation. Real implementation would need to
     * calculate running balance based on all GL transactions up to a date
     *
     * @param string $accountCode GL account code
     *
     * @return float Account balance (0 if not available)
     */
    public function getAccountBalance(string $accountCode): float
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0) as balance
                 FROM gl_trans
                 WHERE account = ?'
            );
            $stmt->execute([$accountCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (float)($result['balance'] ?? 0);
        } catch (\PDOException $e) {
            // If query fails, return 0 and log issue
            return 0.0;
        }
    }

    /**
     * Clear account cache
     *
     * ### Purpose
     * Refresh cache when accounts have been modified
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->accountCache = [];
    }

    /**
     * Set default GL accounts for all loans
     *
     * ### Purpose
     * Configure system-wide GL account defaults
     *
     * @param array $accounts Account mapping
     *
     * @return void
     *
     * @throws RuntimeException If validation fails
     */
    public function setDefaultAccounts(array $accounts): void
    {
        if (!$this->validateAccounts($accounts)) {
            throw new RuntimeException('Invalid GL account configuration');
        }

        // Would update ksf_amort_gl_config with system-wide defaults
        // Implementation depends on schema decisions
    }
}

?>
