<?php
namespace Ksfraser\Amortizations;

class LoanType {
    public $id;
    public $name;
    public $description;
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
    }
}
