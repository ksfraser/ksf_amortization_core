<?php
namespace Ksfraser\Amortizations\Api;

class LoanAnalysisRequest {
    public $loanData;
    public $options;
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
