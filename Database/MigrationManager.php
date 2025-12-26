<?php

namespace Ksfraser\Amortizations\Database;

use PDO;
use RuntimeException;

/**
 * MigrationManager - Database Schema Migration Handler
 *
 * Handles creation and execution of database schema migrations for the
 * KSF Amortization system. Supports multiple database systems:
 * - FrontAccounting (MySQL/MariaDB)
 * - WordPress (MySQL/MariaDB via WPDB)
 * - SuiteCRM (MySQL/MariaDB)
 *
 * Features:
 * - Execute SQL migrations from schema files
 * - Track migration history
 * - Support idempotent operations (CREATE IF NOT EXISTS)
 * - Error handling and rollback support
 * - Multi-database compatibility
 *
 * @author KS Fraser <ksfraser@example.com>
 * @version 1.0.0
 */
class MigrationManager
{
    /**
     * PDO connection instance
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Database prefix (typically "0_" for FrontAccounting)
     *
     * @var string
     */
    private string $prefix = '0_';

    /**
     * Path to schema files directory
     *
     * @var string
     */
    private string $schemaPath;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param string $schemaPath Path to schema files directory
     * @param string $prefix Database table prefix
     */
    public function __construct(
        PDO $pdo,
        string $schemaPath = __DIR__ . '/../',
        string $prefix = '0_'
    ) {
        $this->pdo = $pdo;
        $this->schemaPath = rtrim($schemaPath, '/') . '/';
        $this->prefix = $prefix;

        // Set PDO error mode to exceptions
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Run all required migrations
     *
     * Executes schema creation in order:
     * 1. Event audit tables
     * 2. Payment history tracking
     * 3. Delinquency classification
     * 4. Collection tracking
     * 5. Payment arrangements
     *
     * @return array<string, bool> Migration results keyed by migration name
     * @throws RuntimeException If any migration fails
     */
    public function runAllMigrations(): array
    {
        $results = [];

        try {
            $results['event_audit'] = $this->runMigration('schema_events.sql');
            $results['delinquency'] = $this->runMigration('schema_delinquency.sql');
            $results['success'] = true;

            return $results;
        } catch (RuntimeException $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Run a specific migration from a schema file
     *
     * Reads SQL file, replaces table prefix, and executes statements.
     *
     * @param string $filename Schema filename (without path)
     * @return bool True if successful
     * @throws RuntimeException If file not found or SQL execution fails
     */
    public function runMigration(string $filename): bool
    {
        $filepath = $this->schemaPath . $filename;

        if (!file_exists($filepath)) {
            throw new RuntimeException("Migration file not found: $filepath");
        }

        try {
            $sql = file_get_contents($filepath);
            if ($sql === false) {
                throw new RuntimeException("Failed to read migration file: $filepath");
            }

            // Replace table prefix placeholder
            $sql = str_replace('0_', $this->prefix, $sql);

            // Split into individual statements
            $statements = $this->parseSqlStatements($sql);

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $this->pdo->exec($statement);
                }
            }

            return true;
        } catch (\PDOException $e) {
            throw new RuntimeException(
                "Migration failed for $filename: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $tableName Table name (without prefix)
     * @return bool True if table exists
     * @throws RuntimeException If database query fails
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $fullTableName = $this->prefix . $tableName;

            // Try MySQL-style first (works with MySQL, MariaDB, PostgreSQL)
            $query = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = ?";

            try {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$fullTableName]);
                return $stmt->fetchColumn() > 0;
            } catch (\PDOException $e) {
                // Fall back to SQLite-style query
                if (strpos($e->getMessage(), 'INFORMATION_SCHEMA') !== false) {
                    $query = "SELECT name FROM sqlite_master 
                             WHERE type='table' AND name = ?";
                    $stmt = $this->pdo->prepare($query);
                    $stmt->execute([$fullTableName]);
                    return $stmt->fetchColumn() !== false;
                }
                throw $e;
            }
        } catch (\PDOException $e) {
            throw new RuntimeException("Failed to check table existence: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $tableName Table name (without prefix)
     * @param string $columnName Column name
     * @return bool True if column exists
     * @throws RuntimeException If database query fails
     */
    public function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $fullTableName = $this->prefix . $tableName;

            // Try MySQL-style first (works with MySQL, MariaDB, PostgreSQL)
            $query = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = ? 
                     AND COLUMN_NAME = ?";

            try {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$fullTableName, $columnName]);
                return $stmt->fetchColumn() > 0;
            } catch (\PDOException $e) {
                // Fall back to SQLite-style query using PRAGMA
                if (strpos($e->getMessage(), 'INFORMATION_SCHEMA') !== false) {
                    $query = "PRAGMA table_info($fullTableName)";
                    $stmt = $this->pdo->query($query);
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($columns as $col) {
                        if ($col['name'] === $columnName) {
                            return true;
                        }
                    }
                    return false;
                }
                throw $e;
            }
        } catch (\PDOException $e) {
            throw new RuntimeException("Failed to check column existence: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get migration status report
     *
     * Returns status of all expected tables and columns.
     *
     * @return array<string, mixed> Status report
     */
    public function getMigrationStatus(): array
    {
        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tables' => [],
            'all_required_tables_exist' => true,
        ];

        $requiredTables = [
            'ksf_loan_event_audit' => [
                'id', 'loan_id', 'event_type', 'event_date', 'status', 'metadata'
            ],
            'ksf_payment_history' => [
                'id', 'loan_id', 'total_paid', 'total_payments', 'on_time_count'
            ],
            'ksf_delinquency_status' => [
                'id', 'loan_id', 'status', 'days_overdue', 'risk_score'
            ],
            'ksf_collection_actions' => [
                'id', 'loan_id', 'action_type', 'action_date', 'result'
            ],
            'ksf_payment_arrangement' => [
                'id', 'loan_id', 'arrangement_type', 'status', 'start_date'
            ],
        ];

        foreach ($requiredTables as $tableName => $columns) {
            $tableExists = $this->tableExists($tableName);
            $columnStatus = [];

            if ($tableExists) {
                foreach ($columns as $columnName) {
                    $columnStatus[$columnName] = $this->columnExists($tableName, $columnName);
                    if (!$columnStatus[$columnName]) {
                        $status['all_required_tables_exist'] = false;
                    }
                }
            } else {
                $status['all_required_tables_exist'] = false;
            }

            $status['tables'][$tableName] = [
                'exists' => $tableExists,
                'columns' => $columnStatus
            ];
        }

        return $status;
    }

    /**
     * Parse SQL statements from a multi-statement SQL string
     *
     * Handles:
     * - Multiple statements separated by semicolons
     * - Comments (-- style and block comments)
     * - String literals containing semicolons
     *
     * @param string $sql Raw SQL content
     * @return array<int, string> Array of individual SQL statements
     */
    private function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $inComment = false;
        $inBlockComment = false;

        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            // Handle line comments
            if (strpos(trim($line), '--') === 0) {
                continue;
            }

            for ($i = 0; $i < strlen($line); $i++) {
                $char = $line[$i];
                $nextChar = $i + 1 < strlen($line) ? $line[$i + 1] : '';

                // Block comment handling
                if ($nextChar === '/' && $char === '*') {
                    $inBlockComment = true;
                    $i++; // Skip the *
                    continue;
                }

                if ($char === '*' && $nextChar === '/') {
                    $inBlockComment = false;
                    $i++; // Skip the /
                    continue;
                }

                if ($inBlockComment) {
                    continue;
                }

                // String literal handling
                if ($char === '\'' || $char === '"') {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === $stringChar && ($i === 0 || $line[$i - 1] !== '\\')) {
                        $inString = false;
                    }
                }

                // Statement terminator
                if ($char === ';' && !$inString) {
                    $statements[] = $current . $char;
                    $current = '';
                    continue;
                }

                $current .= $char;
            }

            if (!$inBlockComment && !$inString) {
                $current .= "\n";
            }
        }

        if (!empty(trim($current))) {
            $statements[] = $current;
        }

        return $statements;
    }
}
