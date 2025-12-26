<?php
namespace Ksfraser\Amortizations;

use Ksfraser\Amortizations\Exceptions\DataNotFoundException;
use Ksfraser\Amortizations\Exceptions\DataValidationException;
use Ksfraser\Amortizations\Exceptions\DataPersistenceException;

/**
 * Data Provider Adaptor - Base Class for Platform Implementations
 *
 * Provides common functionality, error handling, and validation
 * for all platform-specific DataProvider implementations.
 *
 * ### Purpose
 * - Reduce code duplication across FA, WordPress, SuiteCRM adaptors
 * - Standardize error handling across all platforms
 * - Provide common validation logic
 * - Define consistent exception handling patterns
 *
 * ### Design Pattern
 * Template Method Pattern: Abstract class defines structure, subclasses implement details
 *
 * ### Subclasses
 * - FADataProvider: Front Accounting
 * - WPDataProvider: WordPress
 * - SuiteCRMDataProvider: SuiteCRM
 *
 * ### Error Handling
 * All subclasses should throw standardized exceptions:
 * - DataNotFoundException: Record not found
 * - DataValidationException: Invalid data
 * - DataPersistenceException: Database/API errors
 *
 * @package   Ksfraser\Amortizations
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-17
 * @abstract
 */
abstract class DataProviderAdaptor implements DataProviderInterface
{
    /**
     * Validate that a value is positive
     *
     * @param mixed $value Value to validate
     * @param string $fieldName Name for error message
     *
     * @return void
     *
     * @throws DataValidationException If value is not positive
     */
    protected function validatePositive($value, string $fieldName): void
    {
        if (!is_numeric($value) || $value <= 0) {
            throw new DataValidationException(
                "{$fieldName} must be positive, got: {$value}"
            );
        }
    }

    /**
     * Validate that a value is non-negative
     *
     * @param mixed $value Value to validate
     * @param string $fieldName Name for error message
     *
     * @return void
     *
     * @throws DataValidationException If value is negative
     */
    protected function validateNonNegative($value, string $fieldName): void
    {
        if (!is_numeric($value) || $value < 0) {
            throw new DataValidationException(
                "{$fieldName} cannot be negative, got: {$value}"
            );
        }
    }

    /**
     * Validate that a value is not empty
     *
     * @param mixed $value Value to validate
     * @param string $fieldName Name for error message
     *
     * @return void
     *
     * @throws DataValidationException If value is empty
     */
    protected function validateNotEmpty($value, string $fieldName): void
    {
        if (empty($value)) {
            throw new DataValidationException("{$fieldName} cannot be empty");
        }
    }

    /**
     * Validate that a value is a valid date
     *
     * @param string $date Date in YYYY-MM-DD format
     * @param string $fieldName Name for error message
     *
     * @return void
     *
     * @throws DataValidationException If date is invalid
     */
    protected function validateDate(string $date, string $fieldName): void
    {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            throw new DataValidationException(
                "{$fieldName} must be valid date in YYYY-MM-DD format, got: {$date}"
            );
        }
    }

    /**
     * Validate that a record exists
     *
     * @param mixed $record Record data (typically array from DB)
     * @param string $entityName Name of entity for error message
     *
     * @return void
     *
     * @throws DataNotFoundException If record is empty/null
     */
    protected function validateRecordExists($record, string $entityName): void
    {
        if (empty($record)) {
            throw new DataNotFoundException(
                "{$entityName} not found"
            );
        }
    }

    /**
     * Validate array has required keys
     *
     * @param array $data Array to validate
     * @param array $requiredKeys Keys that must be present
     *
     * @return void
     *
     * @throws DataValidationException If required keys missing
     */
    protected function validateRequiredKeys(array $data, array $requiredKeys): void
    {
        $missing = array_diff($requiredKeys, array_keys($data));
        if (!empty($missing)) {
            throw new DataValidationException(
                'Missing required fields: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Convert database result to standardized array
     *
     * Ensures all implementations return data in same format.
     *
     * @param mixed $record Raw database result
     *
     * @return array Standardized data array
     */
    protected function standardizeRecord($record): array
    {
        if (is_null($record)) {
            return [];
        }

        if (is_array($record)) {
            return $record;
        }

        // If object with toArray method, use it
        if (method_exists($record, 'toArray')) {
            return $record->toArray();
        }

        // If object with get_properties method (for SuiteCRM beans)
        if (method_exists($record, 'get_properties')) {
            return $record->get_properties();
        }

        // Fallback: try to cast to array
        return (array)$record;
    }

    /**
     * Standardize multiple records
     *
     * @param array $records Array of raw database results
     *
     * @return array Array of standardized records
     */
    protected function standardizeRecords(array $records): array
    {
        return array_map(function($record) {
            return $this->standardizeRecord($record);
        }, $records);
    }

    /**
     * Log database operation for debugging
     *
     * Override in subclasses to use platform-specific logging.
     *
     * @param string $operation Operation name (select, insert, update, delete)
     * @param string $entity Entity name (loan, schedule, event)
     * @param mixed $identifier ID or identifying info
     * @param array $data Data involved in operation (optional)
     *
     * @return void
     */
    protected function logOperation(
        string $operation,
        string $entity,
        $identifier,
        array $data = []
    ): void
    {
        // Default no-op. Subclasses can override to add logging.
        // Example for FA: error_log("OPERATION: $operation on $entity #$identifier");
    }

    /**
     * Get default pagination page size
     *
     * Subclasses can override for platform-specific limits.
     *
     * @return int Default page size
     */
    protected function getDefaultPageSize(): int
    {
        return 50;  // Conservative default
    }

    /**
     * Get maximum allowed page size
     *
     * Subclasses can override for platform-specific limits.
     *
     * @return int Maximum page size
     */
    protected function getMaxPageSize(): int
    {
        return 1000;  // Prevent memory issues
    }

    /**
     * Validate page size is within allowed range
     *
     * @param int $pageSize Requested page size
     *
     * @return int Validated page size
     */
    protected function validatePageSize(int $pageSize): int
    {
        $default = $this->getDefaultPageSize();
        $max = $this->getMaxPageSize();

        if ($pageSize <= 0) {
            return $default;
        }

        return min($pageSize, $max);
    }

    /**
     * Calculate offset from page number and size
     *
     * @param int $page Page number (1-indexed)
     * @param int $pageSize Page size
     *
     * @return int Offset for SQL query (0-indexed)
     */
    protected function calculateOffset(int $page, int $pageSize): int
    {
        return ($page - 1) * $pageSize;
    }
}
