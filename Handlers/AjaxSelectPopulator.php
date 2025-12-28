<?php

namespace Ksfraser\Amortizations\Handlers;

use Ksfraser\HTML\HtmlElement;

/**
 * AjaxSelectPopulator - AJAX-Driven Select Field Handler
 *
 * Encapsulates the JavaScript logic for dynamically populating a select element
 * via AJAX based on another field's value (typically a dropdown change event).
 *
 * Common Use Cases:
 * - Borrower type → Borrower list
 * - Country → State list
 * - Department → Employee list
 * - Category → Product list
 *
 * This handler provides an SRP-based approach:
 * - Generates AJAX fetch function
 * - Handles JSON parsing
 * - Updates target select with options
 * - Centralizes AJAX logic for reusability
 * - Can be extended for different response formats
 *
 * Design Pattern: Template Method
 * - Encapsulates the AJAX function generation
 * - Can be extended for different data formats
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles AJAX select population
 * - Open/Closed: Can be extended for custom response parsing
 * - Liskov Substitution: Can replace HtmlElement
 * - Interface Segregation: Simple, focused interface
 * - Dependency Inversion: Depends on HtmlElement abstraction
 *
 * JavaScript Assumptions:
 * - Source field (e.g., type selector) triggers function on change
 * - Endpoint returns JSON array of objects with 'id' and 'name' properties
 * - Target field is a <select> element that will be populated with options
 *
 * @package    Ksfraser\HTML
 * @author     Kevin Fraser / GitHub Copilot
 * @since      20251220
 * @version    1.0.0
 *
 * @example
 * ```php
 * // Generate AJAX handler for borrower selector
 * $populator = (new AjaxSelectPopulator())
 *     ->setFunctionName('faFetchBorrowers')
 *     ->setSourceFieldId('borrower_type')
 *     ->setTargetFieldId('borrower_id')
 *     ->setEndpoint('borrower_ajax.php')
 *     ->setPlaceholder('Select Borrower');
 * echo $populator->getHtml();
 *
 * // In your view, attach to source field:
 * $typeSelect->addAttribute('onchange', 'faFetchBorrowers()');
 * ```
 */
class AjaxSelectPopulator extends HtmlElement
{
	/**
	 * The name of the JavaScript function to generate
	 *
	 * @var string
	 */
	protected $functionName = 'populateSelect';

	/**
	 * ID of the source field (triggers the AJAX call)
	 *
	 * @var string
	 */
	protected $sourceFieldId = 'source';

	/**
	 * ID of the target select field (receives populated options)
	 *
	 * @var string
	 */
	protected $targetFieldId = 'target';

	/**
	 * AJAX endpoint URL
	 *
	 * @var string
	 */
	protected $endpoint = '';

	/**
	 * Query parameter name for the source field value
	 *
	 * @var string
	 */
	protected $queryParam = 'type';

	/**
	 * Placeholder/default option text
	 *
	 * @var string
	 */
	protected $placeholder = 'Select an option';

	/**
	 * Whether to show a loading indicator
	 *
	 * @var bool
	 */
	protected $showLoadingState = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Set the JavaScript function name
	 *
	 * @param string $name Function name (must be valid JS identifier)
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setFunctionName($name)
	{
		$this->functionName = $name;
		return $this;
	}

	/**
	 * Set the source field ID (field that triggers the AJAX call)
	 *
	 * @param string $id HTML element ID
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setSourceFieldId($id)
	{
		$this->sourceFieldId = $id;
		return $this;
	}

	/**
	 * Set the target field ID (select element to populate)
	 *
	 * @param string $id HTML element ID
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setTargetFieldId($id)
	{
		$this->targetFieldId = $id;
		return $this;
	}

	/**
	 * Set the AJAX endpoint URL
	 *
	 * @param string $url Full or relative URL
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setEndpoint($url)
	{
		$this->endpoint = $url;
		return $this;
	}

	/**
	 * Set the query parameter name for the source value
	 *
	 * @param string $param Query parameter name (default: 'type')
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setQueryParam($param)
	{
		$this->queryParam = $param;
		return $this;
	}

	/**
	 * Set the placeholder/default option text
	 *
	 * @param string $text Placeholder text
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setPlaceholder($text)
	{
		$this->placeholder = $text;
		return $this;
	}

	/**
	 * Enable/disable loading state indicator
	 *
	 * @param bool $show Whether to show loading state
	 * @return AjaxSelectPopulator Fluent interface
	 */
	public function setShowLoadingState($show = true)
	{
		$this->showLoadingState = $show;
		return $this;
	}

	/**
	 * Generate the JavaScript AJAX function
	 *
	 * @return string The complete JavaScript function
	 */
	protected function generateJSFunction()
	{
		$js = "function " . $this->functionName . "() {\n";
		$js .= "  var sourceValue = document.getElementById('" . htmlspecialchars($this->sourceFieldId, ENT_QUOTES, 'UTF-8') . "').value;\n";
		$js .= "  if (!sourceValue) return;\n";
        
		if ($this->showLoadingState) {
			$js .= "  var targetSelect = document.getElementById('" . htmlspecialchars($this->targetFieldId, ENT_QUOTES, 'UTF-8') . "');\n";
			$js .= "  targetSelect.innerHTML = '<option value=\"\">Loading...</option>';\n";
		}
        
		$js .= "  var xhr = new XMLHttpRequest();\n";
		$js .= "  var url = '" . htmlspecialchars($this->endpoint, ENT_QUOTES, 'UTF-8') . "?' + encodeURIComponent('" . $this->queryParam . "=' + sourceValue);\n";
		$js .= "  xhr.open('GET', url);\n";
		$js .= "  xhr.onload = function() {\n";
		$js .= "    if (xhr.status === 200) {\n";
		$js .= "      var data = JSON.parse(xhr.responseText);\n";
		$js .= "      var select = document.getElementById('" . htmlspecialchars($this->targetFieldId, ENT_QUOTES, 'UTF-8') . "');\n";
		$js .= "      select.innerHTML = '<option value=\"\">" . htmlspecialchars($this->placeholder, ENT_QUOTES, 'UTF-8') . "</option>';\n";
		$js .= "      if (Array.isArray(data)) {\n";
		$js .= "        data.forEach(function(item) {\n";
		$js .= "          var option = document.createElement('option');\n";
		$js .= "          option.value = item.id;\n";
		$js .= "          option.textContent = item.name;\n";
		$js .= "          select.appendChild(option);\n";
		$js .= "        });\n";
		$js .= "      }\n";
		$js .= "    } else {\n";
		$js .= "      console.error('AJAX request failed with status:', xhr.status);\n";
		$js .= "    }\n";
		$js .= "  };\n";
		$js .= "  xhr.onerror = function() {\n";
		$js .= "    console.error('AJAX request error');\n";
		$js .= "  };\n";
		$js .= "  xhr.send();\n";
		$js .= "}\n";
        
		return $js;
	}

	/**
	 * Generate the HTML representation (script tag with function)
	 *
	 * @return string
	 */
	public function getHtml()
	{
		$script = new \Ksfraser\HTML\Elements\HtmlScript('text/javascript', $this->generateJSFunction());
		return $script->getHtml();
	}
}
