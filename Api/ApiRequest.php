<?php
namespace Ksfraser\Amortizations\Api;

use JsonSerializable;

/**
 * ApiRequest: Base class for all API requests with validation
 */
abstract class ApiRequest implements JsonSerializable
{
    protected array $data;
    protected array $errors = [];

    /**
     * Create request from array data
     */
    public static function fromArray(array $data): static
    {
        $request = new static();
        $request->data = $data;
        return $request;
    }

    /**
     * Validate request data
     * Override in subclasses to define validation rules
     */
    public function validate(): array
    {
        $this->errors = [];
        $this->validateFields();
        return $this->errors;
    }

    /**
     * Override in subclasses to implement validation
     */
    protected function validateFields(): void
    {
        // Override in subclasses
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Check if request has validation errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get field value safely with type casting
     */
    protected function getField(string $field, mixed $default = null, string $type = 'string'): mixed
    {
        if (!isset($this->data[$field])) {
            return $default;
        }

        $value = $this->data[$field];

        return match ($type) {
            'int', 'integer' => (int)$value,
            'float', 'double' => (float)$value,
            'bool', 'boolean' => (bool)$value,
            'array' => is_array($value) ? $value : [$value],
            'string' => (string)$value,
            default => $value,
        };
    }

    /**
     * Check required field
     */
    protected function requireField(string $field, string $message = ''): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '' || $this->data[$field] === null) {
            $msg = $message ?: ucfirst(str_replace('_', ' ', $field)) . ' is required';
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * Validate string length
     */
    protected function validateLength(string $field, int $min, int $max, string $message = ''): bool
    {
        if (!isset($this->data[$field])) {
            return true; // Will be caught by requireField if needed
        }

        $value = (string)$this->data[$field];
        $length = strlen($value);

        if ($length < $min || $length > $max) {
            $msg = $message ?: "Length must be between $min and $max characters";
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * Validate numeric range
     */
    protected function validateRange(string $field, float $min, float $max, string $message = ''): bool
    {
        if (!isset($this->data[$field])) {
            return true;
        }

        $value = (float)$this->data[$field];

        if ($value < $min || $value > $max) {
            $msg = $message ?: "Value must be between $min and $max";
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    protected function validateDate(string $field, string $message = ''): bool
    {
        if (!isset($this->data[$field])) {
            return true;
        }

        $value = (string)$this->data[$field];
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $value);

        if (!$d || $d->format($format) !== $value) {
            $msg = $message ?: 'Invalid date format, expected YYYY-MM-DD';
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * Validate email format
     */
    protected function validateEmail(string $field, string $message = ''): bool
    {
        if (!isset($this->data[$field])) {
            return true;
        }

        $value = (string)$this->data[$field];
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $msg = $message ?: 'Invalid email format';
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * Validate URL format
     */
    protected function validateUrl(string $field, string $message = ''): bool
    {
        if (!isset($this->data[$field])) {
            return true;
        }

        $value = (string)$this->data[$field];
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $msg = $message ?: 'Invalid URL format';
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * Validate in list
     */
    protected function validateIn(string $field, array $allowedValues, string $message = ''): bool
    {
        if (!isset($this->data[$field])) {
            return true;
        }

        $value = $this->data[$field];
        if (!in_array($value, $allowedValues, true)) {
            $msg = $message ?: 'Invalid value. Allowed: ' . implode(', ', $allowedValues);
            $this->addError($field, $msg);
            return false;
        }
        return true;
    }

    /**
     * JSON serialize for response
     */
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    /**
     * Get all data
     */
    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * CreateLoanRequest: Request for creating a new loan
 */
class CreateLoanRequest extends ApiRequest
{
    protected function validateFields(): void
    {
        $this->requireField('principal', 'Principal amount is required');
        $this->validateRange('principal', 0.01, 999999999, 'Principal must be positive');

        $this->requireField('interest_rate', 'Interest rate is required');
        $this->validateRange('interest_rate', 0, 1, 'Interest rate must be between 0 and 1');

        $this->requireField('term_months', 'Term in months is required');
        $this->validateRange('term_months', 1, 360, 'Term must be between 1 and 360 months');

        $this->requireField('start_date', 'Start date is required');
        $this->validateDate('start_date');

        if (isset($this->data['loan_type'])) {
            $this->validateIn('loan_type', ['auto', 'mortgage', 'personal', 'other']);
        }

        if (isset($this->data['payment_frequency'])) {
            $this->validateIn('payment_frequency', ['monthly', 'bi-weekly', 'weekly', 'daily']);
        }
    }

    public function getPrincipal(): float
    {
        return $this->getField('principal', 0, 'float');
    }

    public function getInterestRate(): float
    {
        return $this->getField('interest_rate', 0, 'float');
    }

    public function getTermMonths(): int
    {
        return $this->getField('term_months', 0, 'int');
    }

    public function getStartDate(): string
    {
        return $this->getField('start_date', '', 'string');
    }

    public function getLoanType(): string
    {
        return $this->getField('loan_type', 'other', 'string');
    }

    public function getPaymentFrequency(): string
    {
        return $this->getField('payment_frequency', 'monthly', 'string');
    }

    public function getLoanDescription(): string
    {
        return $this->getField('description', '', 'string');
    }
}

/**
 * UpdateLoanRequest: Request for updating a loan
 */
class UpdateLoanRequest extends ApiRequest
{
    protected function validateFields(): void
    {
        // Only validate fields that are present
        if (isset($this->data['principal'])) {
            $this->validateRange('principal', 0.01, 999999999, 'Principal must be positive');
        }

        if (isset($this->data['interest_rate'])) {
            $this->validateRange('interest_rate', 0, 1, 'Interest rate must be between 0 and 1');
        }

        if (isset($this->data['start_date'])) {
            $this->validateDate('start_date');
        }

        if (isset($this->data['loan_type'])) {
            $this->validateIn('loan_type', ['auto', 'mortgage', 'personal', 'other']);
        }
    }
}

/**
 * CreateScheduleRequest: Request for creating/regenerating a schedule
 */
class CreateScheduleRequest extends ApiRequest
{
    protected function validateFields(): void
    {
        $this->requireField('loan_id', 'Loan ID is required');
        $this->validateRange('loan_id', 1, 999999999, 'Invalid loan ID');

        if (isset($this->data['recalculate'])) {
            // Optional recalculation flag
        }
    }

    public function getLoanId(): int
    {
        return $this->getField('loan_id', 0, 'int');
    }

    public function shouldRecalculate(): bool
    {
        return $this->getField('recalculate', false, 'bool');
    }
}

/**
 * RecordEventRequest: Request for recording a loan event (extra payment, skip payment, etc)
 */
class RecordEventRequest extends ApiRequest
{
    protected function validateFields(): void
    {
        $this->requireField('loan_id', 'Loan ID is required');
        $this->validateRange('loan_id', 1, 999999999, 'Invalid loan ID');

        $this->requireField('event_type', 'Event type is required');
        $this->validateIn('event_type', ['extra_payment', 'skip_payment', 'rate_change', 'note']);

        $this->requireField('event_date', 'Event date is required');
        $this->validateDate('event_date');

        // Validate event_type specific fields
        if (isset($this->data['event_type'])) {
            if ($this->data['event_type'] === 'extra_payment') {
                $this->requireField('amount', 'Amount is required for extra payment');
                $this->validateRange('amount', 0.01, 999999999, 'Amount must be positive');
            }

            if ($this->data['event_type'] === 'rate_change') {
                $this->requireField('new_rate', 'New rate is required for rate change');
                $this->validateRange('new_rate', 0, 1, 'Rate must be between 0 and 1');
            }
        }
    }

    public function getLoanId(): int
    {
        return $this->getField('loan_id', 0, 'int');
    }

    public function getEventType(): string
    {
        return $this->getField('event_type', '', 'string');
    }

    public function getEventDate(): string
    {
        return $this->getField('event_date', '', 'string');
    }

    public function getAmount(): float
    {
        return $this->getField('amount', 0, 'float');
    }

    public function getNewRate(): float
    {
        return $this->getField('new_rate', 0, 'float');
    }

    public function getNotes(): string
    {
        return $this->getField('notes', '', 'string');
    }
}

/**
 * PaginationRequest: Request for paginated list endpoints
 */
class PaginationRequest extends ApiRequest
{
    protected function validateFields(): void
    {
        if (isset($this->data['page'])) {
            $this->validateRange('page', 1, 999999, 'Page must be at least 1');
        }

        if (isset($this->data['per_page'])) {
            $this->validateRange('per_page', 1, 1000, 'Per page must be between 1 and 1000');
        }

        if (isset($this->data['sort_by'])) {
            // Validate sort field exists (implement in subclass)
        }
    }

    public function getPage(): int
    {
        return $this->getField('page', 1, 'int');
    }

    public function getPerPage(): int
    {
        return $this->getField('per_page', 20, 'int');
    }

    public function getSortBy(): string
    {
        return $this->getField('sort_by', 'id', 'string');
    }

    public function getSortOrder(): string
    {
        $order = $this->getField('sort_order', 'asc', 'string');
        return strtolower($order) === 'desc' ? 'desc' : 'asc';
    }

    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->getPerPage();
    }

    public function getLimit(): int
    {
        return $this->getPerPage();
    }
}
