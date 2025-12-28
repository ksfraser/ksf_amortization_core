<?php
namespace Ksfraser\Amortizations\Models;

/**
 * Represents a single out-of-schedule loan event (skip/extra payment)
 */
class LoanEvent {
    public $id;
    public $loan_id;
    public $event_type; // 'skip' or 'extra'
    public $event_date;
    public $amount;
    public $notes;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->loan_id = $data['loan_id'] ?? null;
        $this->event_type = $data['event_type'] ?? null;
        $this->event_date = $data['event_date'] ?? null;
        $this->amount = $data['amount'] ?? 0.00;
        $this->notes = $data['notes'] ?? '';
    }
}
