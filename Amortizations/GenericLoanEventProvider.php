<?php
namespace Ksfraser\Amortizations;

use Ksfraser\Amortizations\LoanEvent;
use Ksfraser\Amortizations\LoanEventProviderInterface;

class GenericLoanEventProvider implements LoanEventProviderInterface {
    private $pdo;
    private $dbPrefix;
    public function __construct($pdo, $dbPrefix = '') {
        $this->pdo = $pdo;
        $this->dbPrefix = $dbPrefix;
    }
    public function insertLoanEvent(LoanEvent $event): void {
        $sql = "INSERT INTO " . $this->dbPrefix . "ksf_loan_events (loan_id, event_type, event_date, amount, notes) VALUES (:loan_id, :event_type, :event_date, :amount, :notes)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':loan_id' => $event->loan_id,
            ':event_type' => $event->event_type,
            ':event_date' => $event->event_date,
            ':amount' => $event->amount,
            ':notes' => $event->notes
        ]);
    }
    public function getLoanEvents(int $loan_id): array {
        $sql = "SELECT * FROM " . $this->dbPrefix . "ksf_loan_events WHERE loan_id = :loan_id ORDER BY event_date ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':loan_id' => $loan_id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($row) => new LoanEvent($row), $rows);
    }
    public function updateLoanEvent(LoanEvent $event): void {
        $sql = "UPDATE " . $this->dbPrefix . "ksf_loan_events SET event_type = :event_type, event_date = :event_date, amount = :amount, notes = :notes WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $event->id,
            ':event_type' => $event->event_type,
            ':event_date' => $event->event_date,
            ':amount' => $event->amount,
            ':notes' => $event->notes
        ]);
    }
    public function deleteLoanEvent(int $event_id): void {
        $sql = "DELETE FROM " . $this->dbPrefix . "ksf_loan_events WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $event_id]);
    }
}
