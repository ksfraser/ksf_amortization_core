<?php

namespace Ksfraser\Amortizations\Handlers;

use Ksfraser\HTML\HtmlElement;

/**
 * PaymentFrequencyHandler - Payment Frequency to Payments Per Year Converter
 *
 * Encapsulates the JavaScript logic for converting payment frequency selections
 * into the number of payments per year. Commonly used in loan/amortization calculations.
 *
 * This handler provides an SRP-based approach to frequency handling:
 * - Maps frequency names to annual payment counts
 * - Generates the JavaScript conversion function
 * - Handles hidden field population
 * - Centralizes frequency logic for reusability
 *
 * Common Frequencies:
 * - Annual: 1 payment per year
 * - Semi-Annual: 2 payments per year
 * - Monthly: 12 payments per year
 * - Semi-Monthly: 24 payments per year
 * - Bi-Weekly: 26 payments per year
 * - Weekly: 52 payments per year
 *
 * Design Pattern: Template Method
 * - Encapsulates the frequency function generation
 * - Can be extended with custom frequency mappings
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles payment frequency conversion
 * - Open/Closed: Can be extended with custom frequency types
 * - Liskov Substitution: Can replace HtmlElement
 * - Interface Segregation: Simple, focused interface
 * - Dependency Inversion: Depends on HtmlElement abstraction
 *
 * Usage Context:
 * Used in loan/amortization forms where payment frequency determines
 * calculation of periodic payment amounts and total interest.
 *
 * @package    Ksfraser\HTML
 * @author     Kevin Fraser / GitHub Copilot
 * @since      20251220
 * @version    1.0.0
 *
 * @example
 * ```php
 * // Generate the payment frequency handler
 * $handler = (new PaymentFrequencyHandler())
 *     ->setSourceFieldId('payment_frequency')
 *     ->setTargetFieldId('payments_per_year')
 *     ->setFunctionName('updatePaymentsPerYear');
 * echo $handler->getHtml();
 *
 * // In your select, attach to onchange:
 * $freqSelect->addAttribute('onchange', 'updatePaymentsPerYear()');
 * ```
 */
class PaymentFrequencyHandler extends HtmlElement
{
	/**
	 * The name of the JavaScript function to generate
	 *
	 * @var string
	 */
	protected $functionName = 'updatePaymentsPerYear';

	/**
	 * ID of the frequency select field
	 *
	 * @var string
	 */
	protected $sourceFieldId = 'payment_frequency';

	/**
	 * ID of the hidden field to store payments per year
	 *
	 * @var string
	 */
	protected $targetFieldId = 'payments_per_year';

	/**
	 * Mapping of frequency types to annual payment counts
	 *
	 * @var array
	 */
	protected $frequencyMap = [
		'annual' => 1,
		'semi-annual' => 2,
		'monthly' => 12,
		'semi-monthly' => 24,
		'bi-weekly' => 26,
		'weekly' => 52,
	];

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
	 * @return PaymentFrequencyHandler Fluent interface
	 */
	public function setFunctionName($name)
	{
		$this->functionName = $name;
		return $this;
	}

	/**
	 * Set the source field ID (frequency select)
	 *
	 * @param string $id HTML element ID
	 * @return PaymentFrequencyHandler Fluent interface
	 */
	public function setSourceFieldId($id)
	{
		$this->sourceFieldId = $id;
		return $this;
	}

	/**
	 * Set the target field ID (hidden payments_per_year field)
	 *
	 * @param string $id HTML element ID
	 * @return PaymentFrequencyHandler Fluent interface
	 */
	public function setTargetFieldId($id)
	{
		$this->targetFieldId = $id;
		return $this;
	}

	/**
	 * Set custom frequency mapping
	 *
	 * Allows extending the handler with custom frequency types.
	 *
	 * @param array $map Associative array: frequency_name => payments_per_year
	 * @return PaymentFrequencyHandler Fluent interface
	 */
	public function setFrequencyMap(array $map)
	{
		$this->frequencyMap = $map;
		return $this;
	}

	/**
	 * Add a frequency to the mapping
	 *
	 * @param string $frequency Frequency name (must be used in select options)
	 * @param int    $count     Number of payments per year
	 * @return PaymentFrequencyHandler Fluent interface
	 */
	public function addFrequency($frequency, $count)
	{
		$this->frequencyMap[$frequency] = $count;
		return $this;
	}

	/**
	 * Generate the JavaScript frequency conversion function
	 *
	 * @return string The complete JavaScript function
	 */
	protected function generateJSFunction()
	{
		$js = "function " . $this->functionName . "() {\n";
		$js .= "  var freq = document.getElementById('" . htmlspecialchars($this->sourceFieldId, ENT_QUOTES, 'UTF-8') . "').value;\n";
		$js .= "  var val = 12;\n"; // Default to monthly
		$js .= "  switch (freq) {\n";
        
		// Generate switch cases for each frequency
		foreach ($this->frequencyMap as $frequency => $count) {
			$js .= "    case '" . htmlspecialchars($frequency, ENT_QUOTES, 'UTF-8') . "': val = " . (int)$count . "; break;\n";
		}
        
		$js .= "  }\n";
		$js .= "  document.getElementById('" . htmlspecialchars($this->targetFieldId, ENT_QUOTES, 'UTF-8') . "').value = val;\n";
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
