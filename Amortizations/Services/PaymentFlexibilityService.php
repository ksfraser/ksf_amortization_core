<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use DateTimeImmutable;

class PaymentFlexibilityService
{
    public function calculateVariablePaymentAmount(Loan $loan, float $basePayment, float $multiplier): float
    {
        return round($basePayment * $multiplier, 2);
    }

    public function schedulePaymentHoliday(Loan $loan, int $months, DateTimeImmutable $startDate): array
    {
        $extendedPayoffDate = $startDate->add(new \DateInterval('P' . ($loan->getMonths() + $months) . 'M'));

        return [
            'months_skipped' => $months,
            'start_date' => $startDate->format('Y-m-d'),
            'extended_payoff_date' => $extendedPayoffDate->format('Y-m-d'),
            'additional_months' => $months,
        ];
    }

    public function calculatePaymentHolidayImpact(Loan $loan, int $months): array
    {
        $monthlyRate = $loan->getAnnualRate() / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $monthlyRate, $loan->getMonths());
        $additionalInterest = round($loan->getCurrentBalance() * $monthlyRate * $months, 2);
        $newPayoffDate = $loan->getStartDate()->add(new \DateInterval('P' . ($loan->getMonths() + $months) . 'M'));

        return [
            'additional_interest' => $additionalInterest,
            'new_payoff_date' => $newPayoffDate->format('Y-m-d'),
            'holiday_months' => $months,
        ];
    }

    public function createFlexiblePaymentSchedule(Loan $loan, array $customPayments): array
    {
        $balance = $loan->getCurrentBalance();
        $monthlyRate = $loan->getAnnualRate() / 12;
        $schedule = [];
        $month = $loan->getPaymentsMade() + 1;

        foreach ($customPayments as $payment) {
            $interest = round($balance * $monthlyRate, 2);
            $principal = round($payment - $interest, 2);
            $balance = round($balance - $principal, 2);

            $schedule[] = [
                'month' => $month,
                'payment' => $payment,
                'principal' => $principal,
                'interest' => $interest,
                'balance' => max(0, $balance),
            ];

            $month++;
            if ($balance <= 0) break;
        }

        return [
            'schedule' => $schedule,
            'payoff_date' => date('Y-m-d', strtotime('+' . $month . ' months')),
            'total_interest' => round(array_sum(array_column($schedule, 'interest')), 2),
        ];
    }

    public function deferPayment(Loan $loan, DateTimeImmutable $dueDate, int $deferMonths): array
    {
        $monthlyPayment = $this->calculateMonthlyPayment(
            $loan->getPrincipal(),
            $loan->getAnnualRate() / 12,
            $loan->getMonths()
        );

        $deferredAmount = round($monthlyPayment * $deferMonths, 2);

        return [
            'deferred_months' => $deferMonths,
            'deferred_amount' => $deferredAmount,
            'original_due_date' => $dueDate->format('Y-m-d'),
            'new_due_date' => $dueDate->add(new \DateInterval('P' . $deferMonths . 'M'))->format('Y-m-d'),
        ];
    }

    public function calculateCatchUpPaymentPlan(Loan $loan, array $missedPayments, int $catchupMonths): array
    {
        $totalMissed = array_sum($missedPayments);
        $monthlyCatchup = round($totalMissed / $catchupMonths, 2);

        return [
            'total_missed' => round($totalMissed, 2),
            'catchup_months' => $catchupMonths,
            'monthly_catchup' => $monthlyCatchup,
            'new_payoff_date' => date('Y-m-d', strtotime('+' . ($loan->getMonths() + $catchupMonths) . ' months')),
        ];
    }

    public function calculateSkipPaymentImpact(Loan $loan, int $monthsSkipped): array
    {
        $monthlyRate = $loan->getAnnualRate() / 12;
        $interestAccrued = round($loan->getCurrentBalance() * $monthlyRate * $monthsSkipped, 2);

        return [
            'interest_accrued' => $interestAccrued,
            'months_skipped' => $monthsSkipped,
            'extended_payoff' => $monthsSkipped,
        ];
    }

    public function generateFlexPaymentReport(Loan $loan, array $options): array
    {
        $basePayment = $this->calculateMonthlyPayment($loan->getPrincipal(), $loan->getAnnualRate() / 12, $loan->getMonths());

        return [
            'base_scenario' => ['monthly_payment' => round($basePayment, 2)],
            'flexible_scenarios' => $options,
            'comparison' => ['benefit' => 'Flexibility in payment management'],
        ];
    }

    public function validateFlexibilityOption(Loan $loan, string $optionType): bool
    {
        return in_array($optionType, ['skip_payment', 'payment_holiday', 'variable_payment', 'partial_payment']);
    }

    public function calculateMaxDeferralPeriod(Loan $loan): int
    {
        return min(12, (int)($loan->getMonths() / 4));
    }

    public function applyPartialPayment(Loan $loan, float $amount): array
    {
        $monthlyRate = $loan->getAnnualRate() / 12;
        $interestAccrued = round($loan->getCurrentBalance() * $monthlyRate, 2);

        $principalReduction = max(0, $amount - $interestAccrued);
        $newBalance = round($loan->getCurrentBalance() - $principalReduction, 2);

        return [
            'payment_amount' => $amount,
            'interest_portion' => $interestAccrued,
            'principal_reduction' => $principalReduction,
            'new_balance' => $newBalance,
        ];
    }

    public function generateFlexPaymentComparison(Loan $loan, array $flexOptions): array
    {
        $comparison = [];

        foreach ($flexOptions as $index => $option) {
            $comparison[] = [
                'option_index' => $index,
                'type' => $option['type'],
                'impact' => $this->calculateFlexibilityImpact($loan, $option),
            ];
        }

        return $comparison;
    }

    public function exportFlexibilityAnalysisToJSON(Loan $loan): string
    {
        return json_encode([
            'loan_id' => $loan->getId(),
            'flexibility_options' => [
                'skip_payment' => $this->validateFlexibilityOption($loan, 'skip_payment'),
                'payment_holiday' => $this->validateFlexibilityOption($loan, 'payment_holiday'),
                'variable_payment' => $this->validateFlexibilityOption($loan, 'variable_payment'),
            ],
            'export_date' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT);
    }

    public function calculateFeesForFlexibility(Loan $loan, string $flexType): array
    {
        $baseFee = 25.00;

        return [
            'flexibility_type' => $flexType,
            'fee_amount' => $baseFee,
            'description' => "Fee for $flexType option",
        ];
    }

    private function calculateMonthlyPayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate == 0) {
            return $principal / $months;
        }
        return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }

    private function calculateFlexibilityImpact(Loan $loan, array $option): array
    {
        return [
            'total_months' => $loan->getMonths(),
            'impact_description' => 'Flexibility option available',
        ];
    }
}
