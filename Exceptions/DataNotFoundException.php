<?php
namespace Ksfraser\Amortizations\Exceptions;

/**
 * Data Not Found Exception
 *
 * Thrown when a requested record cannot be found in the database.
 *
 * @package   Ksfraser\Amortizations\Exceptions
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-17
 */
class DataNotFoundException extends \RuntimeException
{
    /**
     * Loan not found
     */
    public const LOAN_NOT_FOUND = 'Loan not found';

    /**
     * Schedule row not found
     */
    public const SCHEDULE_NOT_FOUND = 'Schedule row not found';

    /**
     * Event not found
     */
    public const EVENT_NOT_FOUND = 'Event not found';

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable $previous Previous exception for chaining
     */
    public function __construct(
        string $message = 'Record not found',
        int $code = 404,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
