<?php

namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;
use DateTimeImmutable;

/**
 * DelinquencyClassifier - Loan Risk Classification Service
 *
 * Analyzes payment history to classify loan delinquency status, track overdue
 * amounts, and recommend collection actions. Supports portfolio-level risk
 * assessment and early warning systems.
 *
 * Delinquency Tiers:
 * - CURRENT: All payments on schedule
 * - 30_DAYS_PAST_DUE: 1-29 days overdue
 * - 60_DAYS_PAST_DUE: 30-59 days overdue
 * - 90_PLUS_DAYS_PAST_DUE: 90+ days overdue
 *
 * Collection Actions by Tier:
 * - Current: No action required
 * - 30 days: Payment arrangement offered
 * - 60 days: Direct contact required
 * - 90+ days: Formal collection notice, attorney referral for 120+ days
 *
 * Payment Patterns Detected:
 * - CURRENT: All on-time payments
 * - CHRONIC_LATE: 75%+ of payments 10+ days late
 * - RECENT_DETERIORATION: Previously on-time, recent late/missed payments
 * - SPORADIC_PAYER: Random mix of on-time and late/missed
 *
 * @author KS Fraser <ksfraser@example.com>
 * @version 1.0.0
 */
class DelinquencyClassifier
{
    /**
     * Payment history tracker
     *
     * @var PaymentHistoryTracker
     */
    private PaymentHistoryTracker $tracker;

    /**
     * Thresholds for delinquency classification (in days)
     *
     * @var array<string, int>
     */
    private array $thresholds = [
        'current' => 0,
        'thirty_days' => 30,
        'sixty_days' => 60,
        'ninety_days' => 90
    ];

    /**
     * Constructor
     *
     * @param PaymentHistoryTracker $tracker The payment history tracker service
     */
    public function __construct(PaymentHistoryTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Classify the delinquency status of a loan
     *
     * Returns an array with:
     * - status: Classification tier (CURRENT, 30_DAYS_PAST_DUE, etc.)
     * - days_overdue: Number of days past due date
     * - recommendation: Primary collection action
     * - recommendations: Array of suggested actions
     * - risk_score: 0-100 risk rating
     * - missed_payments: Count of completely missed payments
     * - next_action_date: When next collection action should occur
     *
     * @param Loan $loan The loan to classify
     * @return array<string, mixed> Classification result
     */
    public function classify(Loan $loan): array
    {
        $daysOverdue = $this->calculateDaysOverdue($loan);
        $missedCount = $this->countMissedPayments($loan);
        $status = $this->determineStatus($daysOverdue);
        $recommendations = $this->generateRecommendations($status, $daysOverdue, $missedCount);
        $riskScore = $this->calculateRiskScore($status, $daysOverdue, $missedCount);

        return [
            'status' => $status,
            'days_overdue' => $daysOverdue,
            'recommendation' => $recommendations[0] ?? 'No action required',
            'recommendations' => $recommendations,
            'risk_score' => $riskScore,
            'missed_payments' => $missedCount,
            'next_action_date' => $this->calculateNextActionDate($status),
            'last_updated' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Calculate days overdue from the most recent late payment
     *
     * @param Loan $loan The loan to analyze
     * @return int Days past due (0 if current)
     */
    public function calculateDaysOverdue(Loan $loan): int
    {
        $history = $this->tracker->getHistory($loan->getId());

        if (empty($history)) {
            return 0;
        }

        // Find most recent late or missed payment
        $latePayments = array_filter($history, function ($record) {
            return in_array($record['status'], ['late', 'missed'], true);
        });

        if (empty($latePayments)) {
            return 0;
        }

        // Get the most recent late/missed payment
        $latestLate = end($latePayments);
        $eventDate = $latestLate['event_date'];

        if (!$eventDate instanceof DateTimeImmutable) {
            return 0;
        }

        // Calculate days from that date to now
        $now = new DateTimeImmutable();
        $interval = $now->diff($eventDate);

        return (int)$interval->days;
    }

    /**
     * Count the number of missed payments
     *
     * @param Loan $loan The loan to analyze
     * @return int Number of completely missed payments (amount = 0)
     */
    public function countMissedPayments(Loan $loan): int
    {
        $history = $this->tracker->getHistoryByStatus($loan->getId(), 'missed');
        return count($history);
    }

    /**
     * Identify payment patterns to detect chronic issues or deterioration
     *
     * Returns array with:
     * - pattern_type: CURRENT, CHRONIC_LATE, RECENT_DETERIORATION, SPORADIC_PAYER
     * - on_time_percentage: Percentage of on-time payments
     * - late_percentage: Percentage of late payments
     * - average_days_late: Average days late when payment occurs
     * - trend: IMPROVING, STABLE, DETERIORATING
     *
     * @param Loan $loan The loan to analyze
     * @return array<string, mixed> Pattern analysis result
     */
    public function identifyPaymentPattern(Loan $loan): array
    {
        $history = $this->tracker->getHistory($loan->getId());

        if (empty($history)) {
            return [
                'pattern_type' => 'UNKNOWN',
                'on_time_percentage' => 0.0,
                'late_percentage' => 0.0,
                'average_days_late' => 0.0,
                'trend' => 'UNKNOWN'
            ];
        }

        // Count payment statuses
        $totalPayments = count($history);
        $onTimeCount = count(array_filter($history, fn($r) => $r['status'] === 'on_time'));
        $lateCount = count(array_filter($history, fn($r) => $r['status'] === 'late'));
        $missedCount = count(array_filter($history, fn($r) => $r['status'] === 'missed'));

        $onTimePercentage = ($totalPayments > 0) ? ($onTimeCount / $totalPayments) * 100 : 0;
        $latePercentage = ($totalPayments > 0) ? ($lateCount / $totalPayments) * 100 : 0;

        // Calculate average days late (simplified - would need payment date tracking)
        $averageDaysLate = 0.0;
        if ($lateCount > 0) {
            // Rough estimate: average of 15 days late per late payment
            $averageDaysLate = 15.0;
        }

        // Determine pattern
        if ($latePercentage >= 75) {
            $patternType = 'CHRONIC_LATE';
        } elseif ($latePercentage === 0.0 && $missedCount === 0) {
            $patternType = 'CURRENT';
        } elseif ($lateCount > 0 && $onTimePercentage >= 75) {
            $patternType = 'RECENT_DETERIORATION';
        } else {
            $patternType = 'SPORADIC_PAYER';
        }

        // Determine trend (simplified)
        $trend = $this->determineTrend($history);

        return [
            'pattern_type' => $patternType,
            'on_time_percentage' => round($onTimePercentage, 2),
            'late_percentage' => round($latePercentage, 2),
            'missed_percentage' => round(($missedCount / $totalPayments) * 100, 2),
            'average_days_late' => $averageDaysLate,
            'trend' => $trend,
            'total_payments' => $totalPayments
        ];
    }

    /**
     * Generate collection recommendations based on delinquency status
     *
     * @param string $status The delinquency status
     * @param int $daysOverdue Days overdue
     * @param int $missedPayments Count of missed payments
     * @return array<int, string> Array of recommended actions
     */
    private function generateRecommendations(string $status, int $daysOverdue, int $missedPayments): array
    {
        $recommendations = [];

        switch ($status) {
            case 'CURRENT':
                $recommendations[] = 'No action required';
                break;

            case '30_DAYS_PAST_DUE':
                $recommendations[] = 'Send courtesy reminder';
                $recommendations[] = 'Offer payment arrangement';
                break;

            case '60_DAYS_PAST_DUE':
                $recommendations[] = 'Direct contact required';
                $recommendations[] = 'Establish payment plan';
                $recommendations[] = 'Document all collection attempts';
                break;

            case '90_PLUS_DAYS_PAST_DUE':
                $recommendations[] = 'Initiate formal collection notice';
                $recommendations[] = 'Consider attorney referral';
                $recommendations[] = 'Evaluate for charge-off';
                if ($daysOverdue >= 120) {
                    $recommendations[] = 'Refer to external collection agency';
                }
                break;
        }

        // Add missed payment recommendations
        if ($missedPayments >= 3) {
            if (!in_array('Evaluate for charge-off', $recommendations)) {
                $recommendations[] = 'Consider charge-off due to multiple missed payments';
            }
        }

        return $recommendations;
    }

    /**
     * Determine delinquency status based on days overdue
     *
     * @param int $daysOverdue Number of days overdue
     * @return string Status classification
     */
    private function determineStatus(int $daysOverdue): string
    {
        if ($daysOverdue < $this->thresholds['thirty_days']) {
            return 'CURRENT';
        } elseif ($daysOverdue < $this->thresholds['sixty_days']) {
            return '30_DAYS_PAST_DUE';
        } elseif ($daysOverdue < $this->thresholds['ninety_days']) {
            return '60_DAYS_PAST_DUE';
        } else {
            return '90_PLUS_DAYS_PAST_DUE';
        }
    }

    /**
     * Calculate risk score (0-100 scale)
     *
     * Higher score = higher risk
     * 0-20: Low risk
     * 21-50: Medium risk
     * 51-80: High risk
     * 81-100: Critical risk
     *
     * @param string $status Delinquency status
     * @param int $daysOverdue Days overdue
     * @param int $missedPayments Count of missed payments
     * @return int Risk score 0-100
     */
    private function calculateRiskScore(string $status, int $daysOverdue, int $missedPayments): int
    {
        $score = 0;

        // Base score by status
        $baseScores = [
            'CURRENT' => 0,
            '30_DAYS_PAST_DUE' => 25,
            '60_DAYS_PAST_DUE' => 50,
            '90_PLUS_DAYS_PAST_DUE' => 80
        ];

        $score = $baseScores[$status] ?? 0;

        // Additional points for days overdue
        if ($daysOverdue > 90) {
            $score += min(20, floor($daysOverdue / 30)); // Max +20 points
        }

        // Additional points for missed payments
        $score += min(15, $missedPayments * 3); // Max +15 points

        return min(100, $score);
    }

    /**
     * Calculate next action date based on delinquency status
     *
     * @param string $status Delinquency status
     * @return string Next action date (YYYY-MM-DD)
     */
    private function calculateNextActionDate(string $status): string
    {
        $now = new DateTimeImmutable();

        $daysUntilAction = match($status) {
            'CURRENT' => 30,
            '30_DAYS_PAST_DUE' => 7,
            '60_DAYS_PAST_DUE' => 3,
            '90_PLUS_DAYS_PAST_DUE' => 1,
            default => 30
        };

        return $now->modify("+$daysUntilAction days")->format('Y-m-d');
    }

    /**
     * Determine payment trend (IMPROVING, STABLE, DETERIORATING)
     *
     * @param array<int, array> $history Payment history
     * @return string Trend classification
     */
    private function determineTrend(array $history): string
    {
        if (count($history) < 2) {
            return 'STABLE';
        }

        // Split history into recent and older payments
        $recent = array_slice($history, -5);
        $older = array_slice($history, 0, -5);

        if (empty($older)) {
            return 'STABLE';
        }

        // Calculate late payment percentages
        $recentLateCount = count(array_filter($recent, fn($r) => $r['status'] === 'late'));
        $olderLateCount = count(array_filter($older, fn($r) => $r['status'] === 'late'));

        $recentLatePercentage = count($recent) > 0 ? ($recentLateCount / count($recent)) * 100 : 0;
        $olderLatePercentage = count($older) > 0 ? ($olderLateCount / count($older)) * 100 : 0;

        if ($recentLatePercentage > $olderLatePercentage + 20) {
            return 'DETERIORATING';
        } elseif ($recentLatePercentage < $olderLatePercentage - 20) {
            return 'IMPROVING';
        } else {
            return 'STABLE';
        }
    }
}
