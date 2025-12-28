<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

/**
 * EventValidator: Comprehensive event validation
 * 
 * Validates event data before recording:
 * - Event type must be in supported list
 * - Date must be valid and within loan timeframe
 * - Amount validation (if applicable)
 * - Type-specific validation rules
 */
class EventValidator
{
    /**
     * Supported event types
     */
    private const VALID_TYPES = [
        'extra_payment',
        'skip_payment',
        'rate_change',
        'loan_modification',
        'payment_applied',
        'accrual'
    ];

    /**
     * Validate event data
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array Validation errors (empty = valid)
     */
    public function validate(array $eventData, Loan $loan): array
    {
        $errors = [];

        // Event type validation
        if (empty($eventData['event_type'])) {
            $errors['event_type'] = 'Event type is required';
        } elseif (!in_array($eventData['event_type'], self::VALID_TYPES)) {
            $errors['event_type'] = sprintf(
                'Invalid event type. Must be one of: %s',
                implode(', ', self::VALID_TYPES)
            );
        }

        // Date validation
        if (empty($eventData['event_date'])) {
            $errors['event_date'] = 'Event date is required';
        } elseif (!$this->isValidDate($eventData['event_date'])) {
            $errors['event_date'] = 'Invalid date format (YYYY-MM-DD)';
        } elseif ($eventData['event_date'] < $loan->start_date) {
            $errors['event_date'] = 'Event date cannot be before loan start date';
        }

        // Type-specific validation (only if no type error)
        if (empty($errors['event_type'])) {
            $typeErrors = $this->validateByType(
                $eventData['event_type'] ?? '',
                $eventData,
                $loan
            );
            $errors = array_merge($errors, $typeErrors);
        }

        return $errors;
    }

    /**
     * Type-specific validation
     *
     * @param string $eventType
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validateByType(
        string $eventType,
        array $eventData,
        Loan $loan
    ): array {
        switch ($eventType) {
            case 'extra_payment':
                return $this->validateExtraPayment($eventData, $loan);
            case 'skip_payment':
                return $this->validateSkipPayment($eventData, $loan);
            case 'rate_change':
                return $this->validateRateChange($eventData, $loan);
            case 'loan_modification':
                return $this->validateLoanModification($eventData, $loan);
            case 'payment_applied':
                return $this->validatePaymentApplied($eventData, $loan);
            case 'accrual':
                return $this->validateAccrual($eventData, $loan);
            default:
                return [];
        }
    }

    /**
     * Validate extra payment event
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validateExtraPayment(
        array $eventData,
        Loan $loan
    ): array {
        $errors = [];

        if (empty($eventData['amount'])) {
            $errors['amount'] = 'Amount is required for extra payment';
        } elseif (!is_numeric($eventData['amount'])) {
            $errors['amount'] = 'Amount must be numeric';
        } elseif ($eventData['amount'] <= 0) {
            $errors['amount'] = 'Amount must be positive';
        } elseif ($eventData['amount'] > ($loan->current_balance ?? $loan->principal)) {
            $errors['amount'] = 'Amount cannot exceed current loan balance';
        }

        return $errors;
    }

    /**
     * Validate skip payment event
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validateSkipPayment(
        array $eventData,
        Loan $loan
    ): array {
        $errors = [];

        if (empty($eventData['months_to_skip'])) {
            $errors['months_to_skip'] = 'Number of months is required';
        } elseif (!is_numeric($eventData['months_to_skip'])) {
            $errors['months_to_skip'] = 'Must be numeric';
        } elseif ($eventData['months_to_skip'] <= 0) {
            $errors['months_to_skip'] = 'Must skip at least 1 month';
        } elseif ($eventData['months_to_skip'] > 12) {
            $errors['months_to_skip'] = 'Cannot skip more than 12 months';
        }

        return $errors;
    }

    /**
     * Validate rate change event
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validateRateChange(
        array $eventData,
        Loan $loan
    ): array {
        $errors = [];

        if (!isset($eventData['new_rate'])) {
            $errors['new_rate'] = 'New interest rate is required';
        } elseif (!is_numeric($eventData['new_rate'])) {
            $errors['new_rate'] = 'Interest rate must be numeric';
        } elseif ($eventData['new_rate'] < 0 || $eventData['new_rate'] > 1) {
            $errors['new_rate'] = 'Interest rate must be between 0 and 1 (0% to 100%)';
        }

        return $errors;
    }

    /**
     * Validate loan modification event
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validateLoanModification(
        array $eventData,
        Loan $loan
    ): array {
        $errors = [];

        if (empty($eventData['adjustment_type'])) {
            $errors['adjustment_type'] = 'Adjustment type is required';
        } elseif (!in_array($eventData['adjustment_type'], ['principal', 'term'])) {
            $errors['adjustment_type'] = 'Adjustment type must be "principal" or "term"';
        }

        if (empty($eventData['value'])) {
            $errors['value'] = 'Adjustment value is required';
        } elseif (!is_numeric($eventData['value'])) {
            $errors['value'] = 'Value must be numeric';
        }

        return $errors;
    }

    /**
     * Validate payment applied event
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validatePaymentApplied(
        array $eventData,
        Loan $loan
    ): array {
        $errors = [];

        if (empty($eventData['amount'])) {
            $errors['amount'] = 'Amount is required';
        } elseif (!is_numeric($eventData['amount'])) {
            $errors['amount'] = 'Amount must be numeric';
        } elseif ($eventData['amount'] <= 0) {
            $errors['amount'] = 'Amount must be positive';
        }

        if (empty($eventData['applied_to'])) {
            $errors['applied_to'] = 'Applied to field is required';
        } elseif (!in_array($eventData['applied_to'], ['principal', 'interest', 'auto'])) {
            $errors['applied_to'] = 'Applied to must be "principal", "interest", or "auto"';
        }

        return $errors;
    }

    /**
     * Validate accrual event
     *
     * @param array $eventData
     * @param Loan $loan
     * @return array
     */
    private function validateAccrual(
        array $eventData,
        Loan $loan
    ): array {
        $errors = [];

        if (empty($eventData['amount'])) {
            $errors['amount'] = 'Amount is required for accrual';
        } elseif (!is_numeric($eventData['amount'])) {
            $errors['amount'] = 'Amount must be numeric';
        } elseif ($eventData['amount'] <= 0) {
            $errors['amount'] = 'Amount must be positive';
        }

        return $errors;
    }

    /**
     * Check if date string is valid
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Get list of supported event types
     *
     * @return array
     */
    public function getSupportedTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Check if event type is supported
     *
     * @param string $eventType
     * @return bool
     */
    public function isSupportedType(string $eventType): bool
    {
        return in_array($eventType, self::VALID_TYPES);
    }
}
