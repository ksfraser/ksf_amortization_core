<?php

namespace Ksfraser\Amortizations\Handlers;

use Ksfraser\HTML\HtmlElement;
use Ksfraser\HTML\Elements\HtmlScript;

class PaymentFrequencyHandler extends HtmlElement
{
    protected $functionName = 'updatePaymentsPerYear';
    protected $sourceFieldId = 'payment_frequency';
    protected $targetFieldId = 'payments_per_year';
    protected $frequencyMap = [
        'annual' => 1,
        'semi-annual' => 2,
        'monthly' => 12,
        'semi-monthly' => 24,
        'bi-weekly' => 26,
        'weekly' => 52,
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function setFunctionName($name)
    {
        $this->functionName = $name;
        return $this;
    }

    public function setSourceFieldId($id)
    {
        $this->sourceFieldId = $id;
        return $this;
    }

    public function setTargetFieldId($id)
    {
        $this->targetFieldId = $id;
        return $this;
    }

    public function setFrequencyMap(array $map)
    {
        $this->frequencyMap = $map;
        return $this;
    }

    public function addFrequency($frequency, $count)
    {
        $this->frequencyMap[$frequency] = $count;
        return $this;
    }

    // ...rest of the class...
}
