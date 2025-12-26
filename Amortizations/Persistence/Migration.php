<?php

declare(strict_types=1);

namespace Ksfraser\Amortizations\Persistence;

use InvalidArgumentException;

/**
 * Database migration manager
 */
class Migration
{
    protected Database $db;
    protected string $migrationsPath;
    protected array $migrations = [];

    public function __construct(Database $db, string $migrationsPath = '')
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/migrations';
    }

    /**
     * Create migrations table
     */
    public function createMigrationsTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL UNIQUE,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        return $this->db->execute($sql);
    }

    /**
     * Get executed migrations
     */
    public function getExecutedMigrations(): array
    {
        $sql = "SELECT name FROM migrations ORDER BY batch";
        $results = $this->db->fetchAll($sql);
        return array_column($results, 'name');
    }

    /**
     * Register migration
     */
    public function register(string $name, callable $up, callable $down): void
    {
        $this->migrations[$name] = ['up' => $up, 'down' => $down];
    }

    /**
     * Run pending migrations
     */
    public function runPending(): array
    {
        $this->createMigrationsTable();
        $executed = $this->getExecutedMigrations();
        $pending = array_diff(array_keys($this->migrations), $executed);

        $results = [];
        $batch = $this->getNextBatch();

        foreach ($pending as $name) {
            try {
                $this->db->beginTransaction();
                call_user_func($this->migrations[$name]['up'], $this->db);
                
                $this->db->execute(
                    "INSERT INTO migrations (name, batch) VALUES (?, ?)",
                    [$name, $batch]
                );

                $this->db->commit();
                $results[$name] = ['success' => true, 'message' => 'Executed'];
            } catch (\Exception $e) {
                $this->db->rollback();
                $results[$name] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Rollback last batch
     */
    public function rollbackLast(): array
    {
        $this->createMigrationsTable();
        $lastBatch = $this->getLastBatch();

        if ($lastBatch === 0) {
            return ['error' => 'No migrations to rollback'];
        }

        $sql = "SELECT name FROM migrations WHERE batch = ? ORDER BY id DESC";
        $results = $this->db->fetchAll($sql, [$lastBatch]);
        $toRollback = array_column($results, 'name');

        $results = [];

        foreach ($toRollback as $name) {
            if (!isset($this->migrations[$name])) {
                continue;
            }

            try {
                $this->db->beginTransaction();
                call_user_func($this->migrations[$name]['down'], $this->db);
                
                $this->db->execute("DELETE FROM migrations WHERE name = ?", [$name]);

                $this->db->commit();
                $results[$name] = ['success' => true, 'message' => 'Rolled back'];
            } catch (\Exception $e) {
                $this->db->rollback();
                $results[$name] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get next batch number
     */
    protected function getNextBatch(): int
    {
        $result = $this->db->fetchOne("SELECT MAX(batch) as max_batch FROM migrations");
        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Get last batch number
     */
    protected function getLastBatch(): int
    {
        $result = $this->db->fetchOne("SELECT MAX(batch) as max_batch FROM migrations");
        return $result['max_batch'] ?? 0;
    }
}
