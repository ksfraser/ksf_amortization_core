<?php

namespace Ksfraser\Amortizations\Handlers;

use Ksfraser\HTML\HtmlElement;
use Ksfraser\HTML\HtmlFragment;
use Ksfraser\HTML\Elements\HtmlScript;

class SelectEditJSHandler extends HtmlElement
{
    protected $formIdPrefix = 'selector';
    protected $fieldNames = [
        'id' => 'edit_id',
        'selector_name' => 'selector_name',
        'option_name' => 'option_name',
        'option_value' => 'option_value'
    ];

    public function __construct()
    {
        parent::__construct(new HtmlFragment([]));
    }

    public function setFormIdPrefix($prefix)
    {
        $this->formIdPrefix = $prefix;
        return $this;
    }

    public function setFieldNames(array $fields)
    {
        $this->fieldNames = $fields;
        return $this;
    }

    public function addField($paramName, $fieldId)
    {
        $this->fieldNames[$paramName] = $fieldId;
        return $this;
    }

    // ...rest of the class...
}
