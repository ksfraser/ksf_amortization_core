<?php
namespace Ksfraser\Amortizations\Api;

class OriginationRequest {
    public $applicationData;
    public $options;
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
