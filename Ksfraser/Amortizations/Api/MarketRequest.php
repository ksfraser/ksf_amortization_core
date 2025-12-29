<?php
namespace Ksfraser\Amortizations\Api;

class MarketRequest {
    public $marketData;
    public $options;
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
