<?php
namespace Ksfraser\Amortizations;

use Ksfraser\Amortizations\LoanType;
use Ksfraser\Amortizations\InterestCalcFrequency;

interface SelectorDbAdapter {
    public function query(string $sql);
    public function fetch_assoc($result);
    public function escape($value);
    public function execute(string $sql, array $params = []): void;
}

class SelectorProvider {
    private $db;
    private $dbPrefix;
    public function __construct(SelectorDbAdapter $db, $dbPrefix = '') {
        $this->db = $db;
        $this->dbPrefix = $dbPrefix;
    }
    public function getLoanTypes(): array {
        $result = $this->db->query("SELECT * FROM " . $this->dbPrefix . "ksf_amort_loan_types ORDER BY name ASC");
        $types = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $types[] = new LoanType($row);
        }
        return $types;
    }
    public function getInterestCalcFrequencies(): array {
        $result = $this->db->query("SELECT * FROM " . $this->dbPrefix . "ksf_amort_interest_calc_frequencies ORDER BY name ASC");
        $freqs = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $freqs[] = new InterestCalcFrequency($row);
        }
        return $freqs;
    }
    public function addLoanType($name, $description = ''): void {
        $sql = "INSERT INTO " . $this->dbPrefix . "ksf_amort_loan_types (name, description) VALUES (?, ?)";
        $this->db->execute($sql, [$name, $description]);
    }
    public function updateLoanType($id, $name, $description = ''): void {
        $sql = "UPDATE " . $this->dbPrefix . "ksf_amort_loan_types SET name = ?, description = ? WHERE id = ?";
        $this->db->execute($sql, [$name, $description, $id]);
    }
    public function deleteLoanType($id): void {
        $sql = "DELETE FROM " . $this->dbPrefix . "ksf_amort_loan_types WHERE id = ?";
        $this->db->execute($sql, [$id]);
    }
    public function addInterestCalcFrequency($name, $description = ''): void {
        $sql = "INSERT INTO " . $this->dbPrefix . "ksf_amort_interest_calc_frequencies (name, description) VALUES (?, ?)";
        $this->db->execute($sql, [$name, $description]);
    }
    public function updateInterestCalcFrequency($id, $name, $description = ''): void {
        $sql = "UPDATE " . $this->dbPrefix . "ksf_amort_interest_calc_frequencies SET name = ?, description = ? WHERE id = ?";
        $this->db->execute($sql, [$name, $description, $id]);
    }
    public function deleteInterestCalcFrequency($id): void {
        $sql = "DELETE FROM " . $this->dbPrefix . "ksf_amort_interest_calc_frequencies WHERE id = ?";
        $this->db->execute($sql, [$id]);
    }
}
