<?php
namespace Ksfraser\Amortizations\Exceptions;

/**
 * Data Validation Exception
 *
 * Thrown when provided data fails validation.
 *
 * @package   Ksfraser\Amortizations\Exceptions
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-17
 */
class DataValidationException extends \InvalidArgumentException
{
    /**
     * Missing required fields
     */
    public const MISSING_REQUIRED_FIELDS = 'Missing required fields';

    /**
     * Invalid field value
     */
    public const INVALID_FIELD_VALUE = 'Invalid field value';

    /**
     * Invalid date format
     */
    public const INVALID_DATE_FORMAT = 'Invalid date format';

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable $previous Previous exception for chaining
     */
    public function __construct(
        string $message = 'Validation failed',
        int $code = 422,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
