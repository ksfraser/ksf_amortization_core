<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Persistence;

/**
 * Database schema builder for table creation and management
 */
class Schema
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create loans table
     */
    public function createLoansTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS loans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            loan_number TEXT NOT NULL UNIQUE,
            borrower_id INTEGER NOT NULL,
            principal REAL NOT NULL,
            interest_rate REAL NOT NULL,
            term_months INTEGER NOT NULL,
            start_date TEXT NOT NULL,
            status TEXT DEFAULT 'active',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )";

        return $this->db->execute($sql);
    }

    /**
     * Create portfolios table
     */
    public function createPortfoliosTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS portfolios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            manager_id INTEGER NOT NULL,
            description TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )";

        return $this->db->execute($sql);
    }

    /**
     * Create applications table
     */
    public function createApplicationsTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id INTEGER NOT NULL,
            loan_amount REAL NOT NULL,
            loan_purpose TEXT,
            status TEXT DEFAULT 'pending',
            applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
            decided_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )";

        return $this->db->execute($sql);
    }

    /**
     * Create payment schedules table
     */
    public function createPaymentSchedulesTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS payment_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            loan_id INTEGER NOT NULL,
            payment_number INTEGER NOT NULL,
            due_date TEXT NOT NULL,
            payment_amount REAL NOT NULL,
            principal REAL NOT NULL,
            interest REAL NOT NULL,
            balance REAL NOT NULL,
            status TEXT DEFAULT 'pending',
            paid_date TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )";

        return $this->db->execute($sql);
    }

    /**
     * Create audit logs table
     */
    public function createAuditLogsTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            action TEXT NOT NULL,
            changes TEXT,
            user_id TEXT,
            timestamp TEXT DEFAULT CURRENT_TIMESTAMP
        )";

        return $this->db->execute($sql);
    }

    /**
     * Create portfolio loans junction table
     */
    public function createPortfolioLoansTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS portfolio_loans (
            portfolio_id INTEGER NOT NULL,
            loan_id INTEGER NOT NULL,
            added_at TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (portfolio_id, loan_id)
        )";

        return $this->db->execute($sql);
    }

    /**
     * Drop table
     */
    public function dropTable(string $table): bool
    {
        return $this->db->execute("DROP TABLE IF EXISTS $table");
    }
}
