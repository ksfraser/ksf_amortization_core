<?php
/**
 * Amortization GL Posting Controller
 *
 * Integration layer that bridges AmortizationModel with GL posting services.
 * Handles automatic GL posting when amortization schedules are created or updated.
 *
 * ### Responsibility (SRP)
 * - Orchestrate GL posting workflow for amortization
 * - Bridge between AmortizationModel and GLPostingService
 * - Handle event-based GL posting (schedule generation, extra/skip payments)
 * - Provide consistent API for GL posting operations
 *
 * ### Dependencies (DIP)
 * - AmortizationModel: Core amortization calculations
 * - GLPostingService: GL posting orchestration
 * - DataProviderInterface: Data access
 *
 * ### Design Patterns
 * - Facade Pattern: Simplifies GL posting complexity
 * - Observer Pattern (future): Could listen to amortization events
 *
 * ### Usage Example
 * ```php
 * $controller = new AmortizationGLController($amortizationModel, $glPostingService);
 *
 * // Create schedule and post to GL
 * $result = $controller->createLoanAndPostSchedule(
 *     loanData: [...],
 *     glAccounts: ['liability_account' => '2100', ...]
 * );
 *
 * // Handle extra payment with GL reversal and reposting
 * $result = $controller->handleExtraPaymentWithGLUpdate(
 *     loanId: 123,
 *     eventDate: '2025-01-15',
 *     amount: 500.00
 * );
 * ```
 *
 * @package   Ksfraser\Amortizations\FA
 * @author    KSF Development Team
 * @version   1.0.0
 * @since     2025-12-08
 */

namespace Ksfraser\Amortizations\FA;

use Ksfraser\Amortizations\AmortizationModel;
use Ksfraser\Amortizations\DataProviderInterface;
use RuntimeException;
use Exception;
use DateTime;

/**
 * Amortization GL Posting Controller
 */
class AmortizationGLController
{
    /**
     * @var AmortizationModel Core amortization model
     */
    private AmortizationModel $amortizationModel;

    /**
     * @var GLPostingService GL posting service
     */
    private GLPostingService $glPostingService;

    /**
     * @var DataProviderInterface Data access layer
     */
    private DataProviderInterface $dataProvider;

    /**
     * @var array Configuration for GL posting behavior
     */
    private array $config = [
        'auto_post_on_create' => true,
        'auto_post_on_extra' => true,
        'auto_post_on_skip' => false,
        'auto_reverse_on_recalc' => true,
    ];

    /**
     * Constructor with dependency injection
     *
     * @param AmortizationModel $amortizationModel Amortization calculation engine
     * @param GLPostingService $glPostingService GL posting orchestration
     * @param DataProviderInterface $dataProvider Data access layer
     *
     * @throws RuntimeException If dependencies are invalid
     */
    public function __construct(
        AmortizationModel $amortizationModel,
        GLPostingService $glPostingService,
        DataProviderInterface $dataProvider
    ) {
        if (!$amortizationModel) {
            throw new RuntimeException('AmortizationModel required');
        }
        if (!$glPostingService) {
            throw new RuntimeException('GLPostingService required');
        }
        if (!$dataProvider) {
            throw new RuntimeException('DataProviderInterface required');
        }

        $this->amortizationModel = $amortizationModel;
        $this->glPostingService = $glPostingService;
        $this->dataProvider = $dataProvider;
    }

    /**
     * Create loan, generate schedule, and post to GL
     *
     * ### Purpose
     * Complete workflow for setting up a new loan with immediate GL posting.
     *
     * ### Process
     * 1. Create loan in database via AmortizationModel
     * 2. Generate amortization schedule
     * 3. Post all schedule payments to GL
     *
     * ### Returns
     * Array with keys:
     * - success: bool - Whether entire workflow succeeded
     * - loan_id: int - Loan database ID
     * - schedule_created: bool - Whether schedule was created
     * - schedule_count: int - Number of schedule rows
     * - gl_posted: bool - Whether GL posting succeeded
     * - posted_count: int - Number of GL postings
     * - errors: string[] - Any errors encountered
     * - timestamp: string - ISO 8601 timestamp
     *
     * @param array $loanData Loan information (amount, rate, frequency, etc.)
     * @param array $glAccounts GL account mapping
     * @param bool $autoPost Whether to auto-post to GL (default: true)
     *
     * @return array Result with loan_id and posting status
     */
    public function createLoanAndPostSchedule(
        array $loanData,
        array $glAccounts,
        bool $autoPost = true
    ): array {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        $errors = [];

        try {
            // Validate loan data
            if (empty($loanData['amount_financed'])) {
                throw new RuntimeException('Loan amount required');
            }
            if (empty($loanData['interest_rate'])) {
                throw new RuntimeException('Interest rate required');
            }

            // Create loan
            $loanId = $this->amortizationModel->createLoan($loanData);
            if (!$loanId) {
                throw new RuntimeException('Failed to create loan');
            }

            // Generate schedule
            $numberOfPayments = $loanData['number_of_payments'] ?? 12;
            $this->amortizationModel->calculateSchedule($loanId, $numberOfPayments);

            // Verify schedule was created
            $loan = $this->dataProvider->getLoan($loanId);
            if (!$loan) {
                throw new RuntimeException("Loan $loanId not found after creation");
            }

            // Get schedule count
            $scheduleRows = $this->dataProvider->getScheduleRows($loanId);
            $scheduleCount = count($scheduleRows);

            $glResult = ['success' => false, 'total_count' => 0];

            // Post to GL if enabled
            if ($autoPost && $this->config['auto_post_on_create']) {
                $glResult = $this->glPostingService->batchPostLoanPayments(
                    $loanId,
                    null,
                    $glAccounts
                );

                if (!$glResult['success'] && !empty($glResult['errors'])) {
                    $errors = array_merge($errors, $glResult['errors']);
                }
            }

            return [
                'success' => true,
                'loan_id' => $loanId,
                'schedule_created' => true,
                'schedule_count' => $scheduleCount,
                'gl_posted' => $glResult['success'],
                'posted_count' => $glResult['success_count'] ?? 0,
                'errors' => $errors,
                'timestamp' => $timestamp,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'loan_id' => null,
                'schedule_created' => false,
                'schedule_count' => 0,
                'gl_posted' => false,
                'posted_count' => 0,
                'errors' => [$e->getMessage()],
                'timestamp' => $timestamp,
            ];
        }
    }

    /**
     * Handle extra payment with GL updates
     *
     * ### Purpose
     * Record extra payment and update GL postings accordingly.
     *
     * ### Process
     * 1. Record extra payment in AmortizationModel (triggers recalculation)
     * 2. Reverse affected GL postings after event_date
     * 3. Repost adjusted schedule to GL
     *
     * ### Returns
     * Array with keys:
     * - success: bool - Whether entire workflow succeeded
     * - payment_recorded: bool - Whether payment event was recorded
     * - gl_reversed: bool - Whether GL reversals succeeded
     * - gl_reposted: bool - Whether GL reposting succeeded
     * - reversed_count: int - Number of GL reversals
     * - reposted_count: int - Number of GL repostings
     * - errors: string[] - Any errors encountered
     * - timestamp: string - ISO 8601 timestamp
     *
     * @param int $loanId Loan database ID
     * @param string $eventDate Payment date (YYYY-MM-DD format)
     * @param float $amount Extra payment amount
     * @param string $notes Optional notes
     * @param array $glAccounts Optional GL account overrides
     *
     * @return array Result of extra payment processing
     */
    public function handleExtraPaymentWithGLUpdate(
        int $loanId,
        string $eventDate,
        float $amount,
        string $notes = '',
        array $glAccounts = []
    ): array {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        $errors = [];

        try {
            // Validate inputs
            if ($loanId <= 0) {
                throw new RuntimeException('Invalid loan ID');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Extra payment must be positive');
            }

            // Record extra payment (triggers internal recalculation)
            $this->amortizationModel->recordExtraPayment($loanId, $eventDate, $amount, $notes);

            $glReversed = ['success' => false, 'reversed_count' => 0];
            $glReposted = ['success' => false, 'success_count' => 0];

            // Reverse affected GL postings
            if ($this->config['auto_reverse_on_recalc']) {
                $glReversed = $this->glPostingService->reverseSchedulePostings($loanId, $eventDate);

                if (!$glReversed['success'] && !empty($glReversed['errors'])) {
                    $errors = array_merge($errors, $glReversed['errors']);
                }
            }

            // Repost affected schedule to GL
            if ($this->config['auto_post_on_extra']) {
                $glReposted = $this->glPostingService->batchPostLoanPayments(
                    $loanId,
                    null,
                    $glAccounts
                );

                if (!$glReposted['success'] && !empty($glReposted['errors'])) {
                    $errors = array_merge($errors, $glReposted['errors']);
                }
            }

            return [
                'success' => empty($errors),
                'payment_recorded' => true,
                'gl_reversed' => $glReversed['success'],
                'gl_reposted' => $glReposted['success'],
                'reversed_count' => $glReversed['reversed_count'] ?? 0,
                'reposted_count' => $glReposted['success_count'] ?? 0,
                'errors' => $errors,
                'timestamp' => $timestamp,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'payment_recorded' => false,
                'gl_reversed' => false,
                'gl_reposted' => false,
                'reversed_count' => 0,
                'reposted_count' => 0,
                'errors' => [$e->getMessage()],
                'timestamp' => $timestamp,
            ];
        }
    }

    /**
     * Handle skip payment with GL updates
     *
     * ### Purpose
     * Record skip payment and optionally update GL postings.
     * Skip payments typically increase the balance, so GL posting is optional.
     *
     * ### Process
     * 1. Record skip payment in AmortizationModel (triggers recalculation)
     * 2. If auto_post_on_skip enabled:
     *    - Reverse affected GL postings
     *    - Repost adjusted schedule
     *
     * @param int $loanId Loan database ID
     * @param string $eventDate Skip payment date (YYYY-MM-DD)
     * @param float $amount Skipped payment amount
     * @param string $notes Optional notes
     * @param array $glAccounts Optional GL account overrides
     *
     * @return array Result of skip payment processing
     */
    public function handleSkipPaymentWithGLUpdate(
        int $loanId,
        string $eventDate,
        float $amount,
        string $notes = '',
        array $glAccounts = []
    ): array {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        $errors = [];

        try {
            // Validate inputs
            if ($loanId <= 0) {
                throw new RuntimeException('Invalid loan ID');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Skip payment must be positive');
            }

            // Record skip payment (triggers recalculation)
            $this->amortizationModel->recordSkipPayment($loanId, $eventDate, $amount, $notes);

            $glReversed = ['success' => false, 'reversed_count' => 0];
            $glReposted = ['success' => false, 'success_count' => 0];

            // Reverse and repost GL if enabled
            if ($this->config['auto_post_on_skip']) {
                if ($this->config['auto_reverse_on_recalc']) {
                    $glReversed = $this->glPostingService->reverseSchedulePostings($loanId, $eventDate);
                    if (!$glReversed['success']) {
                        $errors = array_merge($errors, $glReversed['errors'] ?? []);
                    }
                }

                $glReposted = $this->glPostingService->batchPostLoanPayments(
                    $loanId,
                    null,
                    $glAccounts
                );

                if (!$glReposted['success']) {
                    $errors = array_merge($errors, $glReposted['errors'] ?? []);
                }
            }

            return [
                'success' => empty($errors),
                'payment_recorded' => true,
                'gl_reversed' => $glReversed['success'],
                'gl_reposted' => $glReposted['success'],
                'reversed_count' => $glReversed['reversed_count'] ?? 0,
                'reposted_count' => $glReposted['success_count'] ?? 0,
                'errors' => $errors,
                'timestamp' => $timestamp,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'payment_recorded' => false,
                'gl_reversed' => false,
                'gl_reposted' => false,
                'reversed_count' => 0,
                'reposted_count' => 0,
                'errors' => [$e->getMessage()],
                'timestamp' => $timestamp,
            ];
        }
    }

    /**
     * Batch post existing loan schedules to GL
     *
     * ### Purpose
     * Post all unposted payments for one or more loans.
     * Used for catchup posting or manual batch posting.
     *
     * @param array $loanIds List of loan IDs to post
     * @param array $glAccounts GL account mapping
     *
     * @return array Summary of posting results
     */
    public function batchPostLoans(array $loanIds, array $glAccounts): array
    {
        $timestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        $results = [];
        $totalPosted = 0;
        $totalFailed = 0;

        foreach ($loanIds as $loanId) {
            $result = $this->glPostingService->batchPostLoanPayments($loanId, null, $glAccounts);
            $results[$loanId] = $result;
            $totalPosted += $result['success_count'] ?? 0;
            $totalFailed += $result['failure_count'] ?? 0;
        }

        return [
            'total_loans' => count($loanIds),
            'total_posted' => $totalPosted,
            'total_failed' => $totalFailed,
            'results' => $results,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Set controller configuration
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     *
     * @return self For method chaining
     */
    public function setConfig(string $key, $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Get controller configuration
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     *
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get underlying services for advanced usage
     *
     * @return array Services
     */
    public function getServices(): array
    {
        return [
            'amortization_model' => $this->amortizationModel,
            'gl_posting_service' => $this->glPostingService,
            'data_provider' => $this->dataProvider,
        ];
    }
}
