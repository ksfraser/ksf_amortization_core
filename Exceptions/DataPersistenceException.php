<?php
namespace Ksfraser\Amortizations\Exceptions;

/**
 * Data Persistence Exception
 *
 * Thrown when a database or API operation fails.
 *
 * @package   Ksfraser\Amortizations\Exceptions
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-17
 */
class DataPersistenceException extends \RuntimeException
{
    /**
     * Insert operation failed
     */
    public const INSERT_FAILED = 'Insert operation failed';

    /**
     * Update operation failed
     */
    public const UPDATE_FAILED = 'Update operation failed';

    /**
     * Delete operation failed
     */
    public const DELETE_FAILED = 'Delete operation failed';

    /**
     * Database connection error
     */
    public const CONNECTION_ERROR = 'Database connection error';

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable $previous Previous exception for chaining
     */
    public function __construct(
        string $message = 'Data persistence failed',
        int $code = 500,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
