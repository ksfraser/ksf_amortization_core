<?php

namespace Ksfraser\Amortizations\Handlers;

use Ksfraser\HTML\HtmlElement;
use Ksfraser\HTML\HtmlFragment;

/**
 * SelectEditJSHandler - Selector Edit JavaScript Handler
 *
 * Encapsulates the JavaScript logic for editing form fields when a table row's
 * edit button is clicked. Generates both the JS function and the script tag.
 *
 * This handler provides an SRP-based approach to managing selector row editing:
 * - Generates the editOption() JavaScript function
 * - Handles form population from row data
 * - Centralizes edit logic for reusability
 * - Can be extended for other edit operations
 *
 * Design Pattern: Template Method
 * - Encapsulates the edit function generation
 * - Can be extended for different field configurations
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles selector edit JS logic
 * - Open/Closed: Can be extended for other field types
 * - Liskov Substitution: Can replace HtmlElement
 * - Interface Segregation: Simple, focused interface
 * - Dependency Inversion: Depends on HtmlElement abstraction
 *
 * Usage Context:
 * Used in selector management views where table rows represent editable options.
 * When an edit button is clicked, this function populates form fields with the
 * selected row's data.
 *
 * @package    Ksfraser\HTML
 * @author     Kevin Fraser / GitHub Copilot
 * @since      20251220
 * @version    1.0.0
 *
 * @example
 * ```php
 * // Generate the edit handler for a selector form
 * $handler = (new SelectEditJSHandler())
 *     ->setFormIdPrefix('selector')
 *     ->setFieldNames(['id', 'selector_name', 'option_name', 'option_value']);
 * echo $handler->getHtml();
 *
 * // Later in the view, when building edit buttons:
 * $editBtn = new EditButton(
 *     new HtmlString('Edit'),
 *     (string)$opt['id'],
 *     sprintf("editOption(%d, '%s', '%s', '%s')", 
 *         $opt['id'],
 *         addslashes($opt['selector_name']),
 *         addslashes($opt['option_name']),
 *         addslashes($opt['option_value'])
 *     )
 * );
 * ```
 */
class SelectEditJSHandler extends HtmlElement
{
	/**
	 * Form field ID prefix
	 *
	 * @var string
	 */
	protected $formIdPrefix = 'selector';

	/**
	 * Array of form field names to populate
	 * Maps function parameter names to form field IDs
	 *
	 * @var array
	 */
	protected $fieldNames = [
		'id' => 'edit_id',
		'selector_name' => 'selector_name',
		'option_name' => 'option_name',
		'option_value' => 'option_value'
	];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(new HtmlFragment([]));
	}

	/**
	 * Set the form ID prefix for field resolution
	 *
	 * @param string $prefix The prefix to use for form field IDs
	 * @return SelectEditJSHandler Fluent interface
	 */
	public function setFormIdPrefix($prefix)
	{
		$this->formIdPrefix = $prefix;
		return $this;
	}

	/**
	 * Set the field names/IDs to populate
	 *
	 * @param array $fields Associative array mapping parameter names to form field IDs
	 * @return SelectEditJSHandler Fluent interface
	 */
	public function setFieldNames(array $fields)
	{
		$this->fieldNames = $fields;
		return $this;
	}

	/**
	 * Add a field to the field mapping
	 *
	 * @param string $paramName  The parameter name used in the JS function
	 * @param string $fieldId    The form field ID to populate
	 * @return SelectEditJSHandler Fluent interface
	 */
	public function addField($paramName, $fieldId)
	{
		$this->fieldNames[$paramName] = $fieldId;
		return $this;
	}

	/**
	 * Generate the JavaScript handler function
	 *
	 * @return string The complete JavaScript function
	 */
	protected function generateJSFunction()
	{
		$js = "function editOption(id, selector, name, value) {\n";
        
		// Generate field population statements
		foreach ($this->fieldNames as $paramName => $fieldId) {
			$varName = $paramName;
            
			// Map common parameter names to variable names
			if ($paramName === 'id') {
				$varName = 'id';
			} elseif ($paramName === 'selector_name') {
				$varName = 'selector';
			} elseif ($paramName === 'option_name') {
				$varName = 'name';
			} elseif ($paramName === 'option_value') {
				$varName = 'value';
			}
            
			$js .= "  document.getElementById('" . htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') . "').value = " . $varName . ";\n";
		}
        
		$js .= "}\n";
        
		return $js;
	}

	/**
	 * Generate the HTML representation (script tag with function)
	 *
	 * @return string
	 */
	public function getHtml(): string
	{
		$script = new \Ksfraser\HTML\Elements\HtmlScript('text/javascript', $this->generateJSFunction());
		return $script->getHtml();
	}
}
