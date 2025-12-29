<?php
namespace Ksfraser\Amortizations\Api;

class PortfolioRequest {
    public $portfolioData;
    public $options;
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
