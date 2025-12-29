<?php

namespace Ksfraser\Amortizations\Handlers;

use Ksfraser\HTML\HtmlElement;
use Ksfraser\HTML\Elements\HtmlScript;

class AjaxSelectPopulator extends HtmlElement
{
    protected $functionName = 'populateSelect';
    protected $sourceFieldId = 'source';
    protected $targetFieldId = 'target';
    protected $endpoint = '';
    protected $queryParam = 'type';
    protected $placeholder = 'Select an option';
    protected $showLoadingState = false;

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

    public function setEndpoint($url)
    {
        $this->endpoint = $url;
        return $this;
    }

    public function setQueryParam($param)
    {
        $this->queryParam = $param;
        return $this;
    }

    public function setPlaceholder($text)
    {
        $this->placeholder = $text;
        return $this;
    }

    public function setShowLoadingState($show = true)
    {
        $this->showLoadingState = $show;
        return $this;
    }

    // ...rest of the class...
}
