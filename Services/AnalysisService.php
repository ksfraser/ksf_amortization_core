<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Repositories\LoanRepository;

/**
 * AnalysisService: Loan comparison and forecasting analysis
 * 
 * Provides:
 * - Multi-loan comparison (APY, term, total cost)
 * - Early payoff scenarios
 * - Interest savings calculations
 * - Loan recommendations
 */
class AnalysisService
{
    /**
     * @var LoanRepository
     */
    private $loanRepository;
    /**
     * @var ScheduleRecalculationService
     */
    private $recalculationService;

    public function __construct(
        LoanRepository $loanRepository,
        ScheduleRecalculationService $recalculationService
    ) {
        $this->loanRepository = $loanRepository;
        $this->recalculationService = $recalculationService;
    }

    /**
     * Compare multiple loans
     *
     * @param array $loanIds
     * @return array Comparison data
     */
    public function compareLoans(array $loanIds): array
    {
        $loans = [];
        $comparison = [
            'loans' => [],
            'summary' => [
                'cheapest_by_interest' => null,
                'shortest_term' => null,
                'lowest_payment' => null
            ],
            'totals' => [
                'combined_principal' => 0,
                'combined_interest' => 0,
                'combined_cost' => 0,
                'average_rate' => 0
            ]
        ];

        // Load all loans
        foreach ($loanIds as $loanId) {
            $loan = $this->loanRepository->get($loanId);
            if ($loan) {
                $loans[] = $loan;
            }
        }

        if (empty($loans)) {
            return ['error' => 'No valid loans found'];
        }

        // Analyze each loan
        $analyses = [];
        $totalRate = 0;

        foreach ($loans as $loan) {
            $analysis = $this->analyzeLoan($loan);
            $analyses[] = $analysis;

            $comparison['loans'][] = [
                'loan_id' => $loan->id,
                'principal' => $loan->principal,
                'interest_rate' => $loan->interest_rate,
                'term_months' => $loan->term_months,
                'monthly_payment' => $analysis['monthly_payment'],
                'total_interest' => $analysis['total_interest'],
                'total_cost' => $analysis['total_cost'],
                'effective_annual_rate' => $analysis['effective_annual_rate']
            ];

            $comparison['totals']['combined_principal'] += $loan->principal;
            $comparison['totals']['combined_interest'] += $analysis['total_interest'];
            $comparison['totals']['combined_cost'] += $analysis['total_cost'];
            $totalRate += $loan->interest_rate;
        }

        // Calculate averages
        $count = count($loans);
        $comparison['totals']['average_rate'] = round($totalRate / $count, 6);

        // Find minimums/maximums
        $interestCosts = array_column($analyses, 'total_interest');
        $terms = array_column($analyses, 'remaining_payments');
        $payments = array_column($analyses, 'monthly_payment');

        $minInterestIdx = array_search(min($interestCosts), $interestCosts);
        $minTermIdx = array_search(min($terms), $terms);
        $minPaymentIdx = array_search(min($payments), $payments);

        $comparison['summary']['cheapest_by_interest'] = $loanIds[$minInterestIdx] ?? null;
        $comparison['summary']['shortest_term'] = $loanIds[$minTermIdx] ?? null;
        $comparison['summary']['lowest_payment'] = $loanIds[$minPaymentIdx] ?? null;

        return $comparison;
    }

    /**
     * Analyze single loan
     *
     * @param Loan $loan
     * @return array
     */
    public function analyzeLoan(Loan $loan): array
    {
        $monthlyPayment = $this->recalculationService->calculateMonthlyPayment($loan);
        $remainingPayments = $this->recalculationService->calculateRemainingPayments($loan);
        $totalInterest = $this->recalculationService->calculateTotalInterest($loan);
        
        $balance = $loan->current_balance ?? $loan->principal;
        $totalCost = $balance + $totalInterest;

        // Effective Annual Rate (considering remaining term)
        $monthlyRate = $loan->interest_rate / 12;
        $effectiveAnnualRate = pow(1 + $monthlyRate, 12) - 1;

        // Time to payoff at current rate
        $months = 0;
        $tempBalance = $balance;
        while ($tempBalance > 0 && $months < 600) {
            $interest = $tempBalance * $monthlyRate;
            $principal = $monthlyPayment - $interest;
            if ($principal <= 0) break;
            $tempBalance -= $principal;
            $months++;
        }

        return [
            'loan_id' => $loan->id,
            'principal' => $loan->principal,
            'current_balance' => $balance,
            'principal_paid' => $loan->principal - $balance,
            'interest_rate' => $loan->interest_rate,
            'effective_annual_rate' => $effectiveAnnualRate,
            'term_months' => $loan->term_months,
            'remaining_payments' => $remainingPayments,
            'monthly_payment' => $monthlyPayment,
            'total_interest' => $totalInterest,
            'total_cost' => $totalCost,
            'months_to_payoff' => $months,
            'payoff_date' => $this->calculatePayoffDate($loan, $months)
        ];
    }

    /**
     * Forecast early payoff with extra payments
     *
     * @param int $loanId
     * @param float $extraPaymentAmount
     * @param string $frequency (monthly, quarterly, annual)
     * @return array Forecast data
     */
    public function forecastEarlyPayoff(
        int $loanId,
        float $extraPaymentAmount,
        string $frequency = 'monthly'
    ): array {
        $loan = $this->loanRepository->get($loanId);
        if (!$loan) {
            return ['error' => 'Loan not found'];
        }

        $monthlyPayment = $this->recalculationService->calculateMonthlyPayment($loan);
        $monthlyRate = $loan->interest_rate / 12;
        $balance = $loan->current_balance ?? $loan->principal;

        // Determine extra payment frequency in months
        switch ($frequency) {
            case 'quarterly':
                $frequencyMonths = 3;
                break;
            case 'annual':
                $frequencyMonths = 12;
                break;
            case 'monthly':
                $frequencyMonths = 1;
                break;
            default:
                $frequencyMonths = 1;
        }

        $months = 0;
        $totalInterest = 0;
        $totalExtraPayments = 0;
        $schedule = [];

        while ($balance > 0 && $months < 600) {
            // Regular payment
            $interest = $balance * $monthlyRate;
            $principal = $monthlyPayment - $interest;

            if ($principal <= 0) break;

            // Extra payment (if on schedule)
            $extra = 0;
            if ($frequencyMonths > 1) {
                if (($months + 1) % $frequencyMonths === 0) {
                    $extra = min($extraPaymentAmount, $balance - $principal);
                }
            } else {
                $extra = min($extraPaymentAmount, $balance - $principal);
            }

            $balance -= ($principal + $extra);
            $totalInterest += $interest;
            $totalExtraPayments += $extra;
            $months++;

            // Record schedule entry
            if ($months <= 12 || $months % 12 === 0) { // First 12 + yearly
                $schedule[] = [
                    'month' => $months,
                    'payment' => $monthlyPayment,
                    'extra' => $extra,
                    'interest' => round($interest, 2),
                    'principal' => round($principal, 2),
                    'balance' => max(0, round($balance, 2))
                ];
            }
        }

        // Calculate original payoff (without extra payments)
        $originalMonthly = $this->recalculationService->calculateMonthlyPayment($loan);
        $originalBalance = $loan->current_balance ?? $loan->principal;
        $originalMonths = 0;
        $originalInterest = 0;
        $tempBalance = $originalBalance;

        while ($tempBalance > 0 && $originalMonths < 600) {
            $interest = $tempBalance * $monthlyRate;
            $principal = $originalMonthly - $interest;
            if ($principal <= 0) break;
            $tempBalance -= $principal;
            $originalInterest += $interest;
            $originalMonths++;
        }

        return [
            'loan_id' => $loanId,
            'original_payoff' => [
                'months' => $originalMonths,
                'total_interest' => round($originalInterest, 2),
                'payoff_date' => $this->calculatePayoffDate($loan, $originalMonths)
            ],
            'with_extra_payments' => [
                'months' => $months,
                'total_interest' => round($totalInterest, 2),
                'total_extra_payments' => round($totalExtraPayments, 2),
                'payoff_date' => $this->calculatePayoffDate($loan, $months)
            ],
            'savings' => [
                'months_saved' => $originalMonths - $months,
                'interest_saved' => round($originalInterest - $totalInterest, 2),
                'percentage_interest_saved' => $originalInterest > 0 
                    ? round((($originalInterest - $totalInterest) / $originalInterest) * 100, 2)
                    : 0
            ],
            'schedule' => array_slice($schedule, 0, 24) // First 24 months
        ];
    }

    /**
     * Calculate payoff date
     *
     * @param Loan $loan
     * @param int $months
     * @return string ISO date
     */
    private function calculatePayoffDate(Loan $loan, int $months): string
    {
        $date = new \DateTime($loan->last_payment_date ?? $loan->start_date);
        $date->add(new \DateInterval('P' . $months . 'M'));
        return $date->format('Y-m-d');
    }

    /**
     * Generate recommendations based on loans
     *
     * @param array $loanIds
     * @return array
     */
    public function generateRecommendations(array $loanIds): array
    {
        $loans = [];
        foreach ($loanIds as $loanId) {
            $loan = $this->loanRepository->get($loanId);
            if ($loan) {
                $loans[] = $loan;
            }
        }

        $recommendations = [
            'total_debt' => 0,
            'highest_rate_loan' => null,
            'shortest_term_loan' => null,
            'actions' => []
        ];

        if (empty($loans)) {
            return $recommendations;
        }

        // Find high-interest loans
        $highestRate = 0;
        $highestRateLoan = null;
        $shortestTerm = PHP_INT_MAX;
        $shortestTermLoan = null;

        foreach ($loans as $loan) {
            $recommendations['total_debt'] += $loan->principal;

            if ($loan->interest_rate > $highestRate) {
                $highestRate = $loan->interest_rate;
                $highestRateLoan = $loan;
            }

            if (($loan->term_months ?? PHP_INT_MAX) < $shortestTerm) {
                $shortestTerm = $loan->term_months ?? PHP_INT_MAX;
                $shortestTermLoan = $loan;
            }
        }

        $recommendations['highest_rate_loan'] = $highestRateLoan ? [
            'id' => $highestRateLoan->id,
            'rate' => $highestRateLoan->interest_rate
        ] : null;

        $recommendations['shortest_term_loan'] = $shortestTermLoan ? [
            'id' => $shortestTermLoan->id,
            'term' => $shortestTermLoan->term_months
        ] : null;

        // Generate actions
        if ($highestRateLoan && $highestRate > 0.06) {
            $recommendations['actions'][] = [
                'action' => 'Consider refinancing',
                'target_loan_id' => $highestRateLoan->id,
                'reason' => 'Interest rate is above 6%',
                'potential_savings' => $this->estimateRefinancingSavings($highestRateLoan)
            ];
        }

        if (count($loans) > 1) {
            $recommendations['actions'][] = [
                'action' => 'Consider consolidation',
                'reason' => 'Multiple loans may be consolidated',
                'potential_savings' => 'Vary based on consolidation terms'
            ];
        }

        if ($recommendations['total_debt'] > 100000) {
            $recommendations['actions'][] = [
                'action' => 'Debt management review',
                'reason' => 'Total debt exceeds $100,000',
                'recommendation' => 'Consider speaking with financial advisor'
            ];
        }

        return $recommendations;
    }

    /**
     * Estimate refinancing savings
     *
     * @param Loan $loan
     * @param float $newRate (default 0.01 less than current)
     * @return array
     */
    private function estimateRefinancingSavings(Loan $loan, float $newRate = null): array
    {
        if ($newRate === null) {
            $newRate = max(0, $loan->interest_rate - 0.01);
        }

        $monthlyRate = $loan->interest_rate / 12;
        $newMonthlyRate = $newRate / 12;

        $balance = $loan->current_balance ?? $loan->principal;
        $remaining = $this->recalculationService->calculateRemainingPayments($loan);

        // Original interest
        $originalPayment = ($monthlyRate > 0)
            ? ($balance * ($monthlyRate * pow(1 + $monthlyRate, $remaining)) / (pow(1 + $monthlyRate, $remaining) - 1))
            : ($balance / $remaining);

        $originalTotal = ($originalPayment * $remaining) - $balance;

        // New interest at lower rate
        $newPayment = ($newMonthlyRate > 0)
            ? ($balance * ($newMonthlyRate * pow(1 + $newMonthlyRate, $remaining)) / (pow(1 + $newMonthlyRate, $remaining) - 1))
            : ($balance / $remaining);

        $newTotal = ($newPayment * $remaining) - $balance;

        return [
            'current_rate' => $loan->interest_rate,
            'new_rate' => $newRate,
            'current_total_interest' => round($originalTotal, 2),
            'new_total_interest' => round($newTotal, 2),
            'interest_savings' => round($originalTotal - $newTotal, 2),
            'monthly_payment_reduction' => round($originalPayment - $newPayment, 2)
        ];
    }

    /**
     * Get debt payoff timeline
     *
     * @param array $loanIds
     * @return array
     */
    public function getDebtPayoffTimeline(array $loanIds): array
    {
        $timeline = [
            'start_date' => null,
            'end_date' => null,
            'loans' => [],
            'milestones' => []
        ];

        $loans = [];
        $earliestStart = null;
        $latestEnd = null;

        foreach ($loanIds as $loanId) {
            $loan = $this->loanRepository->get($loanId);
            if ($loan) {
                $loans[] = $loan;
                $remaining = $this->recalculationService->calculateRemainingPayments($loan);
                $payoffDate = $this->calculatePayoffDate($loan, $remaining);

                if (!$earliestStart || $loan->start_date < $earliestStart) {
                    $earliestStart = $loan->start_date;
                }

                if (!$latestEnd || $payoffDate > $latestEnd) {
                    $latestEnd = $payoffDate;
                }

                $timeline['loans'][] = [
                    'loan_id' => $loan->id,
                    'start_date' => $loan->start_date,
                    'payoff_date' => $payoffDate,
                    'duration_months' => $remaining
                ];
            }
        }

        $timeline['start_date'] = $earliestStart;
        $timeline['end_date'] = $latestEnd;

        // Calculate milestones (25%, 50%, 75% payoff)
        if (!empty($loans)) {
            $totalPrincipal = array_sum(array_column($loans, 'principal'));

            $timeline['milestones'] = [
                ['percentage' => 25, 'target_date' => $this->estimatePayoffDate(25, $timeline['start_date'], $timeline['end_date'])],
                ['percentage' => 50, 'target_date' => $this->estimatePayoffDate(50, $timeline['start_date'], $timeline['end_date'])],
                ['percentage' => 75, 'target_date' => $this->estimatePayoffDate(75, $timeline['start_date'], $timeline['end_date'])],
            ];
        }

        return $timeline;
    }

    /**
     * Estimate payoff date for percentage
     *
     * @param int $percentage
     * @param string $startDate
     * @param string $endDate
     * @return string
     */
    private function estimatePayoffDate(int $percentage, ?string $startDate, ?string $endDate): string
    {
        if (!$startDate || !$endDate) {
            return '';
        }

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        $totalDays = $interval->days;
        $targetDays = (int)($totalDays * ($percentage / 100));

        $targetDate = new \DateTime($startDate);
        $targetDate->add(new \DateInterval('P' . $targetDays . 'D'));

        return $targetDate->format('Y-m-d');
    }
}
