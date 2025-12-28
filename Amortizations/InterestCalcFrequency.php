<?php
namespace Ksfraser\Amortizations;

class InterestCalcFrequency {
    public $id;
    public $name;
    public $description;
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
    }
}
