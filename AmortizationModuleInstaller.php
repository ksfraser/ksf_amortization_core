<?php
namespace Ksfraser\Amortizations;

class AmortizationModuleInstaller
{
    private $pdo;
    private $schemaFiles;

    public function __construct($pdo, $schemaFiles = [])
    {
        $this->pdo = $pdo;
        $this->schemaFiles = $schemaFiles;
    }

    public function install()
    {
        foreach ($this->schemaFiles as $file) {
            if (!file_exists($file)) continue;
            $sql = file_get_contents($file);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt) {
                    $this->runIfNotExists($stmt);
                }
            }
        }
    }

    private function runIfNotExists($sql)
    {
        // Extract table name from CREATE TABLE statement
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/i', $sql, $matches) ||
            preg_match('/CREATE TABLE `(\w+)`/i', $sql, $matches)) {
            $table = $matches[1];
            if ($this->tableExists($table)) return;
        }
        $this->pdo->exec($sql);
    }

    private function tableExists($table)
    {
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }
}
