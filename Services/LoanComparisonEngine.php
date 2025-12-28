<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Utils\DecimalCalculator;
use DateTime;

/**
 * LoanComparisonEngine
 *
 * Compares multiple loan offers side-by-side with cost-benefit analysis,
 * APR validation, and recommendation scoring.
 */
class LoanComparisonEngine
{
    /**
     * @var DecimalCalculator
     */
    private $calculator;

    public function __construct()
    {
        $this->calculator = new DecimalCalculator();
    }

    /**
     * Compare loan offers side-by-side
     */
    public function compareLoanOffersSideBySide(array $offers, array $fees): array
    {
        $comparison = [];

        foreach ($offers as $index => $offer) {
            $fee = $fees[$index] ?? 0;
            $monthlyPayment = $this->calculateMonthlyPayment(
                $offer->getPrincipal(),
                $offer->getAnnualRate(),
                $offer->getMonths()
            );
            $totalInterest = $this->calculateTotalInterest(
                $offer->getPrincipal(),
                $monthlyPayment,
                $offer->getMonths()
            );
            $totalCost = $this->calculator->round(
                $this->calculator->add($totalInterest, $fee),
                2
            );

            $comparison[] = [
                'offer_id' => $offer->getId(),
                'principal' => $offer->getPrincipal(),
                'rate' => $offer->getAnnualRate(),
                'term_months' => $offer->getMonths(),
                'term_years' => round($offer->getMonths() / 12),
                'monthly_payment' => $this->calculator->round($monthlyPayment, 2),
                'total_interest' => $this->calculator->round($totalInterest, 2),
                'fees' => $this->calculator->round($fee, 2),
                'total_cost' => $totalCost,
                'effective_apr' => $this->calculateEffectiveAPRWithFees(
                    $offer->getPrincipal(),
                    $offer->getAnnualRate(),
                    $fee,
                    $offer->getMonths()
                ),
            ];
        }

        return $comparison;
    }

    /**
     * Calculate monthly payment using amortization formula
     */
    public function calculateMonthlyPayment(float $principal, float $rate, int $months): float
    {
        if ($months <= 0) {
            return 0;
        }

        $monthlyRate = $this->calculator->divide($rate, 12);

        if ($monthlyRate == 0) {
            return $this->calculator->divide($principal, $months);
        }

        $numerator = $this->calculator->multiply($monthlyRate, pow(1 + $monthlyRate, $months));
        $denominator = $this->calculator->subtract(pow(1 + $monthlyRate, $months), 1);

        return $this->calculator->round(
            $this->calculator->multiply($principal, $this->calculator->divide($numerator, $denominator)),
            2
        );
    }

    /**
     * Calculate total interest paid
     */
    private function calculateTotalInterest(float $principal, float $monthlyPayment, int $months): float
    {
        $totalPaid = $this->calculator->multiply($monthlyPayment, $months);
        return $this->calculator->subtract($totalPaid, $principal);
    }

    /**
     * Calculate total cost (principal + interest + fees)
     */
    public function calculateTotalCost(Loan $offer, float $fees, float $avgRate): float
    {
        $monthlyPayment = $this->calculateMonthlyPayment(
            $offer->getPrincipal(),
            $offer->getAnnualRate(),
            $offer->getMonths()
        );

        $totalInterest = $this->calculateTotalInterest(
            $offer->getPrincipal(),
            $monthlyPayment,
            $offer->getMonths()
        );

        return $this->calculator->round(
            $this->calculator->add($totalInterest, $fees),
            2
        );
    }

    /**
     * Calculate effective APR including fees
     */
    public function calculateEffectiveAPRWithFees(float $principal, float $rate, float $fees, int $months): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0;
        }

        // Simplified: adjust rate based on fee impact
        $monthlyPayment = $this->calculateMonthlyPayment($principal, $rate, $months);
        $totalPaid = $this->calculator->multiply($monthlyPayment, $months);
        $actualCost = $this->calculator->add(
            $this->calculator->subtract($totalPaid, $principal),
            $fees
        );

        $effectiveRate = $this->calculator->divide(
            $this->calculator->divide($actualCost, $principal),
            ($months / 12)
        );

        return $this->calculator->round($effectiveRate, 4);
    }

    /**
     * Compare total interest costs
     */
    public function compareTotalInterestCosts(array $offers, float $avgRate): array
    {
        $comparison = [];

        foreach ($offers as $offer) {
            $monthlyPayment = $this->calculateMonthlyPayment(
                $offer->getPrincipal(),
                $offer->getAnnualRate(),
                $offer->getMonths()
            );

            $totalInterest = $this->calculateTotalInterest(
                $offer->getPrincipal(),
                $monthlyPayment,
                $offer->getMonths()
            );

            $comparison[] = [
                'offer_id' => $offer->getId(),
                'principal' => $offer->getPrincipal(),
                'rate' => $offer->getAnnualRate(),
                'months' => $offer->getMonths(),
                'monthly_payment' => $this->calculator->round($monthlyPayment, 2),
                'total_interest' => $this->calculator->round($totalInterest, 2),
                'interest_as_percent_of_principal' => $this->calculator->round(
                    $this->calculator->divide($totalInterest, $offer->getPrincipal()),
                    4
                ),
            ];
        }

        return $comparison;
    }

    /**
     * Calculate break-even point between two offers
     */
    public function calculateBreakEvenPoint(Loan $offer1, Loan $offer2, array $fees): int
    {
        $fee1 = $fees[0] ?? 0;
        $fee2 = $fees[1] ?? 0;

        $payment1 = $this->calculateMonthlyPayment(
            $offer1->getPrincipal(),
            $offer1->getAnnualRate(),
            $offer1->getMonths()
        );

        $payment2 = $this->calculateMonthlyPayment(
            $offer2->getPrincipal(),
            $offer2->getAnnualRate(),
            $offer2->getMonths()
        );

        $feeDifference = abs($fee1 - $fee2);
        $paymentDifference = abs($payment1 - $payment2);

        if ($paymentDifference == 0) {
            return 0;
        }

        return (int)ceil($this->calculator->divide($feeDifference, $paymentDifference));
    }

    /**
     * Generate offer recommendation
     */
    public function generateOfferRecommendation(array $offers, array $fees, string $goal): array
    {
        $comparison = $this->compareLoanOffersSideBySide($offers, $fees);

        $bestOffer = $comparison[0];
        $key = match ($goal) {
            'minimize_cost' => 'total_cost',
            'minimize_payment' => 'monthly_payment',
            'minimize_term' => 'term_months',
            default => 'total_cost',
        };

        foreach ($comparison as $offer) {
            if ($offer[$key] < $bestOffer[$key]) {
                $bestOffer = $offer;
            }
        }

        $savings = $this->calculateCostSavingsBetweenOffers(
            $offers[0],
            $offers[array_key_first(
                array_filter($comparison, function($o) use ($bestOffer) { return $o['offer_id'] === $bestOffer['offer_id']; })
            )],
            $fees[0],
            $fees[array_key_first(
                array_filter($comparison, function($o) use ($bestOffer) { return $o['offer_id'] === $bestOffer['offer_id']; })
            )],
            $offers[0]->getAnnualRate()
        );

        return [
            'recommended_offer_id' => $bestOffer['offer_id'],
            'reason' => "Lowest {$goal}",
            'metric_value' => $bestOffer[$key],
            'savings' => $this->calculator->round($savings, 2),
            'all_offers' => $comparison,
        ];
    }

    /**
     * Calculate cost savings between offers
     */
    public function calculateCostSavingsBetweenOffers(
        Loan $offer1,
        Loan $offer2,
        float $fee1,
        float $fee2,
        float $avgRate
    ): float {
        $cost1 = $this->calculateTotalCost($offer1, $fee1, $avgRate);
        $cost2 = $this->calculateTotalCost($offer2, $fee2, $avgRate);

        return max(0, $this->calculator->subtract($cost1, $cost2));
    }

    /**
     * Validate offer comparison assumptions
     */
    public function validateOfferAssumptions(array $offers): array
    {
        $issues = [];

        if (empty($offers)) {
            $issues[] = 'No offers to compare';
            return ['valid' => false, 'issues' => $issues];
        }

        $firstPrincipal = $offers[0]->getPrincipal();
        foreach ($offers as $offer) {
            if ($offer->getPrincipal() !== $firstPrincipal) {
                $issues[] = 'Offers have different principal amounts';
                break;
            }
        }

        foreach ($offers as $offer) {
            if ($offer->getAnnualRate() < 0 || $offer->getAnnualRate() > 0.25) {
                $issues[] = "Offer {$offer->getId()}: Invalid rate {$offer->getAnnualRate()}";
            }
            if ($offer->getMonths() <= 0) {
                $issues[] = "Offer {$offer->getId()}: Invalid term";
            }
        }

        return [
            'valid' => count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Generate offer comparison matrix
     */
    public function generateOfferComparisonMatrix(array $offers, array $fees): array
    {
        $comparison = $this->compareLoanOffersSideBySide($offers, $fees);

        return [
            'generated_date' => (new DateTime())->format('Y-m-d H:i:s'),
            'offers' => $comparison,
            'metrics' => [
                'cheapest_total_cost' => min(array_column($comparison, 'total_cost')),
                'lowest_rate' => min(array_column($comparison, 'rate')),
                'shortest_term' => min(array_column($comparison, 'term_months')),
                'lowest_payment' => min(array_column($comparison, 'monthly_payment')),
            ],
        ];
    }

    /**
     * Calculate payment-to-principal ratio
     */
    public function calculatePaymentToPrincipalRatio(float $principal, float $rate, int $months): float
    {
        $monthlyPayment = $this->calculateMonthlyPayment($principal, $rate, $months);
        $totalPaid = $this->calculator->multiply($monthlyPayment, $months);

        return $this->calculator->round($this->calculator->divide($totalPaid, $principal), 4);
    }

    /**
     * Rank offers by weighted scoring criteria
     */
    public function rankOffersByScoring(array $offers, array $fees, array $weights): array
    {
        $comparison = $this->compareLoanOffersSideBySide($offers, $fees);
        $costs = array_column($comparison, 'total_cost');
        $payments = array_column($comparison, 'monthly_payment');
        $terms = array_column($comparison, 'term_months');

        $minCost = min($costs);
        $maxCost = max($costs);
        $minPayment = min($payments);
        $maxPayment = max($payments);
        $minTerm = min($terms);
        $maxTerm = max($terms);

        foreach ($comparison as &$offer) {
            $costScore = 1 - ($offer['total_cost'] - $minCost) / ($maxCost - $minCost);
            $paymentScore = 1 - ($offer['monthly_payment'] - $minPayment) / ($maxPayment - $minPayment);
            $termScore = 1 - ($offer['term_months'] - $minTerm) / ($maxTerm - $minTerm);

            $offer['score'] = $this->calculator->round(
                $costScore * $weights['cost'] +
                $paymentScore * $weights['payment'] +
                $termScore * $weights['term'],
                4
            );
        }

        usort($comparison, function($a, $b) {
            if ($a['score'] == $b['score']) return 0;
            return ($a['score'] < $b['score']) ? 1 : -1;
        });
        return $comparison;
    }

    /**
     * Export comparison to JSON
     */
    public function exportToJSON(array $matrix): string
    {
        return json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Calculate loan affordability metric
     */
    public function calculateLoanAffordability(Loan $offer, float $fees, float $annualIncome): array
    {
        $monthlyPayment = $this->calculateMonthlyPayment(
            $offer->getPrincipal(),
            $offer->getAnnualRate(),
            $offer->getMonths()
        );

        $monthlyIncome = $this->calculator->divide($annualIncome, 12);
        $dtiRatio = $this->calculator->divide($monthlyPayment, $monthlyIncome);

        return [
            'monthly_payment' => $this->calculator->round($monthlyPayment, 2),
            'monthly_income' => $this->calculator->round($monthlyIncome, 2),
            'dti_ratio' => $this->calculator->round($dtiRatio, 4),
            'affordable' => $dtiRatio <= 0.43,  // Standard lending threshold
            'total_fees' => $this->calculator->round($fees, 2),
        ];
    }
}
