<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Models\Event;

/**
 * ScheduleRecalculationService: Handle schedule recalculation after events
 * 
 * Triggers recalculation for different event types:
 * - Extra payment: Reduce remaining balance, recalculate schedule
 * - Skip payment: Extend term, recalculate with interest
 * - Rate change: Update interest rate, recalculate all payments
 * - Loan modification: Adjust principal/term, regenerate schedule
 */
class ScheduleRecalculationService
{
    /**
     * Determine if recalculation needed for event type
     *
     * @param string $eventType
     * @return bool
     */
    public function shouldRecalculate(string $eventType): bool
    {
        $recalculatableTypes = [
            'extra_payment',
            'skip_payment',
            'rate_change',
            'loan_modification'
        ];
        
        return in_array($eventType, $recalculatableTypes);
    }

    /**
     * Recalculate schedule after event
     *
     * @param Loan $loan
     * @param Event $event
     * @param array $eventData
     * @return Loan Updated loan with recalculated schedule
     */
    public function recalculate(
        Loan $loan,
        Event $event,
        array $eventData
    ): Loan {
        if (!$this->shouldRecalculate($event->event_type)) {
            return $loan;
        }

        return match ($event->event_type) {
            'extra_payment' => $this->recalculateAfterExtraPayment($loan, $event, $eventData),
            'skip_payment' => $this->recalculateAfterSkipPayment($loan, $event, $eventData),
            'rate_change' => $this->recalculateAfterRateChange($loan, $event, $eventData),
            'loan_modification' => $this->recalculateAfterModification($loan, $event, $eventData),
            default => $loan
        };
    }

    /**
     * Recalculate schedule after extra payment
     * 
     * Extra payment reduces principal, remaining schedule recalculated
     * with same interest rate and term (unless early payoff)
     *
     * @param Loan $loan
     * @param Event $event
     * @param array $eventData
     * @return Loan
     */
    private function recalculateAfterExtraPayment(
        Loan $loan,
        Event $event,
        array $eventData
    ): Loan {
        $amount = $eventData['amount'] ?? 0;
        
        // Update current balance
        $loan->current_balance = max(0, ($loan->current_balance ?? $loan->principal) - $amount);
        
        // Mark for recalculation
        $loan->needs_recalculation = true;
        $loan->recalculation_reason = 'extra_payment';
        $loan->recalculation_date = $event->event_date;
        
        return $loan;
    }

    /**
     * Recalculate schedule after skip payment
     * 
     * Skip payment extends term by specified months, remaining balance
     * continues to accrue interest at current rate
     *
     * @param Loan $loan
     * @param Event $event
     * @param array $eventData
     * @return Loan
     */
    private function recalculateAfterSkipPayment(
        Loan $loan,
        Event $event,
        array $eventData
    ): Loan {
        $monthsToSkip = $eventData['months_to_skip'] ?? 1;
        
        // Calculate estimated interest for skipped months
        $monthlyRate = $loan->interest_rate / 12;
        $currentBalance = $loan->current_balance ?? $loan->principal;
        $interestAccrued = 0;
        
        for ($i = 0; $i < $monthsToSkip; $i++) {
            $interestAccrued += $currentBalance * $monthlyRate;
            $currentBalance += $currentBalance * $monthlyRate;
        }
        
        // Update loan
        $loan->current_balance = $currentBalance;
        $loan->term_months = ($loan->term_months ?? 0) + $monthsToSkip;
        $loan->needs_recalculation = true;
        $loan->recalculation_reason = 'skip_payment';
        $loan->recalculation_date = $event->event_date;
        
        return $loan;
    }

    /**
     * Recalculate schedule after rate change
     * 
     * Interest rate is updated, all remaining payments recalculated
     * with new rate
     *
     * @param Loan $loan
     * @param Event $event
     * @param array $eventData
     * @return Loan
     */
    private function recalculateAfterRateChange(
        Loan $loan,
        Event $event,
        array $eventData
    ): Loan {
        $newRate = $eventData['new_rate'] ?? $loan->interest_rate;
        $oldRate = $loan->interest_rate;
        
        // Store old rate for audit
        $loan->previous_interest_rate = $oldRate;
        $loan->interest_rate = $newRate;
        $loan->rate_change_date = $event->event_date;
        $loan->needs_recalculation = true;
        $loan->recalculation_reason = 'rate_change';
        $loan->recalculation_date = $event->event_date;
        
        return $loan;
    }

    /**
     * Recalculate schedule after loan modification
     * 
     * Principal or term adjusted, full schedule regeneration required
     *
     * @param Loan $loan
     * @param Event $event
     * @param array $eventData
     * @return Loan
     */
    private function recalculateAfterModification(
        Loan $loan,
        Event $event,
        array $eventData
    ): Loan {
        $adjustmentType = $eventData['adjustment_type'] ?? 'principal';
        $value = $eventData['value'] ?? 0;
        
        if ($adjustmentType === 'principal') {
            // Adjust principal (principal can increase or decrease)
            $loan->principal = max(0, $loan->principal + $value);
            $loan->current_balance = $loan->principal;
        } elseif ($adjustmentType === 'term') {
            // Adjust term (add/remove months)
            $loan->term_months = max(1, ($loan->term_months ?? 0) + (int)$value);
        }
        
        $loan->needs_recalculation = true;
        $loan->recalculation_reason = 'loan_modification';
        $loan->recalculation_date = $event->event_date;
        $loan->modification_type = $adjustmentType;
        $loan->modification_value = $value;
        
        return $loan;
    }

    /**
     * Calculate remaining payments
     *
     * @param Loan $loan
     * @return int Number of remaining payments
     */
    public function calculateRemainingPayments(Loan $loan): int
    {
        $currentDate = new \DateTime($loan->last_payment_date ?? $loan->start_date);
        $maturityDate = new \DateTime($loan->maturity_date ?? date('Y-m-d', strtotime('+' . ($loan->term_months ?? 0) . ' months')));
        
        $diff = $currentDate->diff($maturityDate);
        $monthsDifference = $diff->m + ($diff->y * 12);
        
        return max(0, $monthsDifference);
    }

    /**
     * Calculate early payoff date based on extra payment
     *
     * @param Loan $loan
     * @param float $extraPaymentAmount
     * @return string ISO date of early payoff
     */
    public function calculateEarlyPayoffDate(
        Loan $loan,
        float $extraPaymentAmount
    ): string {
        $balance = $loan->current_balance ?? $loan->principal;
        $monthlyRate = $loan->interest_rate / 12;
        $regularPayment = $this->calculateMonthlyPayment($loan);
        
        $totalPayment = $regularPayment + $extraPaymentAmount;
        $months = 0;
        
        while ($balance > 0 && $months < 600) { // 50 year safety limit
            $interest = $balance * $monthlyRate;
            $principal = $totalPayment - $interest;
            
            if ($principal <= 0) {
                break;
            }
            
            $balance -= $principal;
            $months++;
        }
        
        $payoffDate = new \DateTime($loan->last_payment_date ?? $loan->start_date);
        $payoffDate->add(new \DateInterval('P' . $months . 'M'));
        
        return $payoffDate->format('Y-m-d');
    }

    /**
     * Calculate monthly payment
     *
     * @param Loan $loan
     * @return float
     */
    public function calculateMonthlyPayment(Loan $loan): float
    {
        $principal = $loan->current_balance ?? $loan->principal;
        $rate = $loan->interest_rate / 12;
        $term = $this->calculateRemainingPayments($loan);
        
        if ($rate === 0.0) {
            return $term > 0 ? $principal / $term : 0;
        }
        
        if ($term === 0) {
            return 0;
        }
        
        $numerator = $rate * pow(1 + $rate, $term);
        $denominator = pow(1 + $rate, $term) - 1;
        
        return $principal * ($numerator / $denominator);
    }

    /**
     * Calculate interest paid to date
     *
     * @param Loan $loan
     * @return float
     */
    public function calculateInterestPaidToDate(Loan $loan): float
    {
        // This would normally be calculated from the schedule
        // For now, simple estimation
        $amortized = ($loan->principal - ($loan->current_balance ?? $loan->principal));
        
        // This is simplified; real calculation requires access to payment history
        return max(0, $amortized * ($loan->interest_rate / 2));
    }

    /**
     * Calculate total interest for loan life
     *
     * @param Loan $loan
     * @return float
     */
    public function calculateTotalInterest(Loan $loan): float
    {
        $monthlyPayment = $this->calculateMonthlyPayment($loan);
        $term = $this->calculateRemainingPayments($loan);
        
        return max(0, ($monthlyPayment * $term) - ($loan->current_balance ?? $loan->principal));
    }

    /**
     * Calculate interest savings with extra payment
     *
     * @param Loan $loan
     * @param float $extraPaymentAmount
     * @return float
     */
    public function calculateInterestSavings(
        Loan $loan,
        float $extraPaymentAmount
    ): float {
        $interestWithoutExtra = $this->calculateTotalInterest($loan);
        
        // Create copy of loan with extra payment applied
        $loanWithExtra = clone $loan;
        $loanWithExtra->current_balance -= $extraPaymentAmount;
        
        $interestWithExtra = $this->calculateTotalInterest($loanWithExtra);
        
        return max(0, $interestWithoutExtra - $interestWithExtra);
    }
}
