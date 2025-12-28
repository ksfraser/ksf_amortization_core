<?php

namespace Ksfraser\Amortizations\Repositories;

/**
 * DelinquencyRepository Interface
 *
 * Defines contract for persisting and retrieving loan delinquency status and classifications.
 * Supports risk assessment, collection management, and portfolio analysis.
 *
 * Responsibilities:
 * - Persist delinquency classifications
 * - Query loans by delinquency status
 * - Track delinquency trends
 * - Support collection workflow
 * - Generate risk reports
 *
 * @author KS Fraser <ksfraser@example.com>
 * @version 1.0.0
 */
interface DelinquencyRepository
{
    /**
     * Save or update delinquency status for a loan
     *
     * @param int $loanId The loan identifier
     * @param array $delinquencyData Status details (status, days_overdue, risk_score, etc.)
     * @return int The ID of the saved record
     * @throws \RuntimeException If database operation fails
     */
    public function saveDelinquencyStatus(int $loanId, array $delinquencyData): int;

    /**
     * Retrieve current delinquency status for a loan
     *
     * @param int $loanId The loan identifier
     * @return array|null The delinquency status or null if not found
     * @throws \RuntimeException If database query fails
     */
    public function getDelinquencyStatus(int $loanId): ?array;

    /**
     * Retrieve all loans with a specific delinquency status
     *
     * @param string $status The delinquency status (CURRENT, 30_DAYS_PAST_DUE, etc.)
     * @param int|null $limit Maximum number of loans to return
     * @param int|null $offset Number of loans to skip
     * @return array<int, array> Array of loans with matching status
     * @throws \RuntimeException If database query fails
     */
    public function getLoansByStatus(string $status, ?int $limit = null, ?int $offset = null): array;

    /**
     * Retrieve all loans with a specific risk level
     *
     * @param string $riskLevel The risk level (LOW, MEDIUM, HIGH, CRITICAL)
     * @param int|null $limit Maximum number of loans to return
     * @param int|null $offset Number of loans to skip
     * @return array<int, array> Array of loans with matching risk level
     * @throws \RuntimeException If database query fails
     */
    public function getLoansByRiskLevel(string $riskLevel, ?int $limit = null, ?int $offset = null): array;

    /**
     * Retrieve loans by payment pattern type
     *
     * @param string $patternType The pattern type (CHRONIC_LATE, RECENT_DETERIORATION, etc.)
     * @return array<int, array> Array of loans matching the pattern
     * @throws \RuntimeException If database query fails
     */
    public function getLoansByPaymentPattern(string $patternType): array;

    /**
     * Retrieve loans due for next collection action
     *
     * Returns loans where next_action_date is today or earlier.
     *
     * @param int|null $limit Maximum number of loans to return
     * @param int|null $offset Number of loans to skip
     * @return array<int, array> Array of loans due for action
     * @throws \RuntimeException If database query fails
     */
    public function getLoansDueForAction(?int $limit = null, ?int $offset = null): array;

    /**
     * Get count of loans by status
     *
     * Returns distribution of loans across all delinquency statuses.
     *
     * @return array<string, int> Count keyed by status (e.g., ['CURRENT' => 950, '30_DAYS_PAST_DUE' => 45])
     * @throws \RuntimeException If database query fails
     */
    public function getCountByStatus(): array;

    /**
     * Get count of loans by risk level
     *
     * Returns distribution of loans across risk levels.
     *
     * @return array<string, int> Count keyed by risk level
     * @throws \RuntimeException If database query fails
     */
    public function getCountByRiskLevel(): array;

    /**
     * Record a collection action for a loan
     *
     * @param int $loanId The loan identifier
     * @param array $actionData Action details (type, description, date, result, etc.)
     * @return int The ID of the recorded action
     * @throws \RuntimeException If database operation fails
     */
    public function recordCollectionAction(int $loanId, array $actionData): int;

    /**
     * Retrieve collection actions for a loan
     *
     * @param int $loanId The loan identifier
     * @param int|null $limit Maximum number of actions to return
     * @return array<int, array> Array of collection actions
     * @throws \RuntimeException If database query fails
     */
    public function getCollectionActions(int $loanId, ?int $limit = null): array;

    /**
     * Get most recent collection action for a loan
     *
     * @param int $loanId The loan identifier
     * @return array|null The most recent action or null if none found
     * @throws \RuntimeException If database query fails
     */
    public function getMostRecentAction(int $loanId): ?array;

    /**
     * Create or update a payment arrangement
     *
     * @param int $loanId The loan identifier
     * @param array $arrangementData Arrangement details (type, status, dates, etc.)
     * @return int The ID of the arrangement record
     * @throws \RuntimeException If database operation fails
     */
    public function savePaymentArrangement(int $loanId, array $arrangementData): int;

    /**
     * Retrieve active payment arrangements for a loan
     *
     * @param int $loanId The loan identifier
     * @return array|null The active arrangement or null if none exists
     * @throws \RuntimeException If database query fails
     */
    public function getActiveArrangement(int $loanId): ?array;

    /**
     * Get portfolio-level delinquency statistics
     *
     * Returns aggregate metrics for all loans:
     * - total_loans
     * - current_count / percentage
     * - 30_day_count / percentage
     * - 60_day_count / percentage
     * - 90_day_count / percentage
     * - high_risk_count
     * - average_days_overdue
     * - total_amount_at_risk
     *
     * @return array<string, mixed> Portfolio statistics
     * @throws \RuntimeException If database query fails
     */
    public function getPortfolioStatistics(): array;

    /**
     * Get risk score distribution
     *
     * Returns histogram of risk scores for portfolio analysis.
     *
     * @param int $bucketSize Size of score buckets (default 10 = 0-10, 11-20, 21-30, etc.)
     * @return array<string, int> Distribution keyed by score range
     * @throws \RuntimeException If database query fails
     */
    public function getRiskScoreDistribution(int $bucketSize = 10): array;

    /**
     * Update delinquency status for a loan
     *
     * @param int $loanId The loan identifier
     * @param array $updateData Fields to update
     * @return bool True if successful
     * @throws \RuntimeException If database operation fails
     */
    public function updateDelinquencyStatus(int $loanId, array $updateData): bool;

    /**
     * Clear delinquency data for a loan (typically for loan payoff or cleanup)
     *
     * @param int $loanId The loan identifier
     * @return bool True if successful
     * @throws \RuntimeException If database operation fails
     */
    public function clearDelinquencyStatus(int $loanId): bool;
}
