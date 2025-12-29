<?php
namespace Ksfraser\Amortizations\Api;

class LoanAnalysisResponse {
    public $success;
    public $analysis;
    public $errors = [];
    public $timestamp;
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        $this->timestamp = date('c');
    }
    public function toArray() {
        return [
            'success' => $this->success,
            'analysis' => $this->analysis,
            'errors' => $this->errors,
            'timestamp' => $this->timestamp
        ];
    }
}
