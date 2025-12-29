<?php
namespace Ksfraser\Amortizations\Api;

class ReportRequest {
    public $reportType;
    public $parameters;
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
