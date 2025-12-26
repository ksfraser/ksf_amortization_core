<?php
namespace Ksfraser\Amortizations;

class SelectorDbAdapterPDO implements SelectorDbAdapter {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    public function query(string $sql) {
        return $this->pdo->query($sql);
    }
    public function fetch_assoc($result) {
        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function escape($value) {
        // PDO uses prepared statements, so escaping is not needed
        return $value;
    }
    public function execute(string $sql, array $params = []): void {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
