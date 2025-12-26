<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Persistence;

use PDO;
use PDOException;
use InvalidArgumentException;

/**
 * Database connection manager with transaction support
 */
class Database
{
    private PDO $connection;
    private int $transactionDepth = 0;
    private array $lastError = [];

    public function __construct(string $dsn, string $username = '', string $password = '')
    {
        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            throw new InvalidArgumentException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Begin transaction (supports nested)
     */
    public function beginTransaction(): bool
    {
        try {
            if ($this->transactionDepth === 0) {
                $this->connection->beginTransaction();
            } else {
                $this->connection->exec("SAVEPOINT level{$this->transactionDepth}");
            }
            $this->transactionDepth++;
            return true;
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        try {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                return $this->connection->commit();
            } else {
                $this->connection->exec("RELEASE SAVEPOINT level{$this->transactionDepth}");
                return true;
            }
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        try {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                return $this->connection->rollBack();
            } else {
                $this->connection->exec("ROLLBACK TO SAVEPOINT level{$this->transactionDepth}");
                return true;
            }
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Execute query
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Fetch one row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            return null;
        }
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            $this->lastError = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            return [];
        }
    }

    /**
     * Get last insert ID
     */
    public function getLastInsertId(string $name = ''): string
    {
        return $this->connection->lastInsertId($name);
    }

    /**
     * Get last error
     */
    public function getLastError(): array
    {
        return $this->lastError;
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }
}

/**
 * Base repository for CRUD operations
 */
abstract class Repository
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find by primary key
     */
    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Find all records
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$limit, $offset]);
    }

    /**
     * Find by criteria
     */
    public function findBy(array $criteria): array
    {
        $conditions = array_map(fn($key) => "$key = ?", array_keys($criteria));
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $conditions);
        return $this->db->fetchAll($sql, array_values($criteria));
    }

    /**
     * Count records
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table}";
            $result = $this->db->fetchOne($sql);
        } else {
            $conditions = array_map(fn($key) => "$key = ?", array_keys($criteria));
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE " . implode(' AND ', $conditions);
            $result = $this->db->fetchOne($sql, array_values($criteria));
        }
        return $result['count'] ?? 0;
    }

    /**
     * Create record
     */
    public function create(array $data): ?int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        if ($this->db->execute($sql, array_values($data))) {
            return (int)$this->db->getLastInsertId();
        }
        return null;
    }

    /**
     * Update record
     */
    public function update(int|string $id, array $data): bool
    {
        $sets = array_map(fn($key) => "$key = ?", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        return $this->db->execute($sql, $values);
    }

    /**
     * Delete record
     */
    public function delete(int|string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->execute($sql, [$id]);
    }

    /**
     * Delete by criteria
     */
    public function deleteBy(array $criteria): int
    {
        if (empty($criteria)) {
            return 0;
        }

        $conditions = array_map(fn($key) => "$key = ?", array_keys($criteria));
        $sql = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $conditions);
        
        if ($this->db->execute($sql, array_values($criteria))) {
            // Return 1 to indicate success (PDO doesn't give row count easily)
            return 1;
        }
        return 0;
    }
}

/**
 * Loan repository
 */
class LoanRepository extends Repository
{
    protected string $table = 'loans';

    /**
     * Find active loans
     */
    public function findActive(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Find by borrower
     */
    public function findByBorrower(int $borrowerId): array
    {
        return $this->findBy(['borrower_id' => $borrowerId]);
    }

    /**
     * Get loan with schedule
     */
    public function findWithSchedule(int $loanId): ?array
    {
        $loan = $this->find($loanId);
        if (!$loan) {
            return null;
        }

        $scheduleRepo = new PaymentScheduleRepository($this->db);
        $loan['schedule'] = $scheduleRepo->findByLoan($loanId);

        return $loan;
    }
}

/**
 * Portfolio repository
 */
class PortfolioRepository extends Repository
{
    protected string $table = 'portfolios';

    /**
     * Find by manager
     */
    public function findByManager(int $managerId): array
    {
        return $this->findBy(['manager_id' => $managerId]);
    }

    /**
     * Find with loans
     */
    public function findWithLoans(int $portfolioId): ?array
    {
        $portfolio = $this->find($portfolioId);
        if (!$portfolio) {
            return null;
        }

        $sql = "SELECT l.* FROM loans l 
                JOIN portfolio_loans pl ON l.id = pl.loan_id 
                WHERE pl.portfolio_id = ?";
        $portfolio['loans'] = $this->db->fetchAll($sql, [$portfolioId]);

        return $portfolio;
    }
}

/**
 * Application repository
 */
class ApplicationRepository extends Repository
{
    protected string $table = 'applications';

    /**
     * Find pending applications
     */
    public function findPending(int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'pending' LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Find by applicant
     */
    public function findByApplicant(int $applicantId): array
    {
        return $this->findBy(['applicant_id' => $applicantId]);
    }

    /**
     * Find approved applications
     */
    public function findApproved(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'approved' LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
}

/**
 * Payment schedule repository
 */
class PaymentScheduleRepository extends Repository
{
    protected string $table = 'payment_schedules';

    /**
     * Find by loan
     */
    public function findByLoan(int $loanId): array
    {
        return $this->findBy(['loan_id' => $loanId]);
    }

    /**
     * Find upcoming payments
     */
    public function findUpcomingPayments(string $dueDate): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE due_date <= ? AND status = 'pending'";
        return $this->db->fetchAll($sql, [$dueDate]);
    }
}

/**
 * Audit log repository
 */
class AuditLogRepository extends Repository
{
    protected string $table = 'audit_logs';
    protected string $primaryKey = 'audit_id';

    /**
     * Log action
     */
    public function log(string $entity, int|string $entityId, string $action, array $changes = [], ?string $userId = null): ?int
    {
        $data = [
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'action' => $action,
            'changes' => json_encode($changes),
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return $this->create($data);
    }

    /**
     * Get entity history
     */
    public function getHistory(string $entity, int|string $entityId): array
    {
        return $this->findBy(['entity_type' => $entity, 'entity_id' => $entityId]);
    }

    /**
     * Get user actions
     */
    public function getUserActions(string $userId, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY timestamp DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }
}
