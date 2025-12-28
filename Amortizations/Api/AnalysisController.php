<?php
namespace Ksfraser\Amortizations\Api;

use Ksfraser\Amortizations\Services\AnalysisService;
use Ksfraser\Amortizations\Repositories\LoanRepository;

/**
 * AnalysisController: API endpoints for loan analysis
 * 
 * GET /api/v1/analysis/compare       - Compare multiple loans
 * POST /api/v1/analysis/forecast     - Forecast with extra payments
 * GET /api/v1/analysis/recommendations - Get recommendations
 */
class AnalysisController extends BaseApiController
{
    private AnalysisService $analysisService;
    private LoanRepository $loanRepository;

    public function __construct(
        AnalysisService $analysisService = null,
        LoanRepository $loanRepository = null
    ) {
        $this->analysisService = $analysisService;
        $this->loanRepository = $loanRepository ?? new LoanRepository();
    }

    /**
     * GET /api/v1/analysis/compare?loan_ids=1,2,3
     * Compare multiple loans
     */
    public function compare(array $queryParams = []): ApiResponse
    {
        try {
            // Validate loan_ids parameter
            if (empty($queryParams['loan_ids'])) {
                throw new ValidationException(
                    'Missing required parameter',
                    ['loan_ids' => 'loan_ids parameter is required (comma-separated)']
                );
            }

            // Parse loan IDs
            $loanIdString = $queryParams['loan_ids'];
            $loanIds = array_filter(
                array_map('intval', explode(',', $loanIdString)),
                fn($id) => $id > 0
            );

            if (empty($loanIds)) {
                throw new ValidationException(
                    'Invalid loan IDs',
                    ['loan_ids' => 'loan_ids must be comma-separated positive integers']
                );
            }

            // Run comparison
            $comparison = $this->analysisService->compareLoans($loanIds);

            if (isset($comparison['error'])) {
                throw new ResourceNotFoundException($comparison['error']);
            }

            return ApiResponse::success(
                $comparison,
                'Loan comparison completed successfully'
            );
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to compare loans: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/analysis/forecast
     * Forecast early payoff with extra payments
     * 
     * Request body:
     * {
     *   "loan_id": 1,
     *   "extra_payment_amount": 500,
     *   "frequency": "monthly"
     * }
     */
    public function forecast(array $requestData = []): ApiResponse
    {
        try {
            // Validate required fields
            if (empty($requestData['loan_id'])) {
                throw new ValidationException(
                    'Missing required field',
                    ['loan_id' => 'loan_id is required']
                );
            }

            if (empty($requestData['extra_payment_amount'])) {
                throw new ValidationException(
                    'Missing required field',
                    ['extra_payment_amount' => 'extra_payment_amount is required']
                );
            }

            $loanId = intval($requestData['loan_id']);
            $extraAmount = floatval($requestData['extra_payment_amount']);
            $frequency = $requestData['frequency'] ?? 'monthly';

            // Validate values
            if ($loanId <= 0) {
                throw new ValidationException(
                    'Invalid loan ID',
                    ['loan_id' => 'loan_id must be positive']
                );
            }

            if ($extraAmount <= 0) {
                throw new ValidationException(
                    'Invalid extra payment amount',
                    ['extra_payment_amount' => 'extra_payment_amount must be positive']
                );
            }

            if (!in_array($frequency, ['monthly', 'quarterly', 'annual'])) {
                throw new ValidationException(
                    'Invalid frequency',
                    ['frequency' => 'frequency must be monthly, quarterly, or annual']
                );
            }

            // Verify loan exists
            $loan = $this->loanRepository->get($loanId);
            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            // Run forecast
            $forecast = $this->analysisService->forecastEarlyPayoff(
                $loanId,
                $extraAmount,
                $frequency
            );

            if (isset($forecast['error'])) {
                throw new ResourceNotFoundException($forecast['error']);
            }

            return ApiResponse::success(
                $forecast,
                'Early payoff forecast completed successfully'
            );
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate forecast: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/analysis/recommendations?loan_ids=1,2,3
     * Get recommendations based on loans
     */
    public function recommendations(array $queryParams = []): ApiResponse
    {
        try {
            // Validate loan_ids parameter
            if (empty($queryParams['loan_ids'])) {
                throw new ValidationException(
                    'Missing required parameter',
                    ['loan_ids' => 'loan_ids parameter is required (comma-separated)']
                );
            }

            // Parse loan IDs
            $loanIdString = $queryParams['loan_ids'];
            $loanIds = array_filter(
                array_map('intval', explode(',', $loanIdString)),
                fn($id) => $id > 0
            );

            if (empty($loanIds)) {
                throw new ValidationException(
                    'Invalid loan IDs',
                    ['loan_ids' => 'loan_ids must be comma-separated positive integers']
                );
            }

            // Generate recommendations
            $recommendations = $this->analysisService->generateRecommendations($loanIds);

            return ApiResponse::success(
                $recommendations,
                'Recommendations generated successfully'
            );
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate recommendations: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/analysis/timeline?loan_ids=1,2,3
     * Get debt payoff timeline
     */
    public function timeline(array $queryParams = []): ApiResponse
    {
        try {
            // Validate loan_ids parameter
            if (empty($queryParams['loan_ids'])) {
                throw new ValidationException(
                    'Missing required parameter',
                    ['loan_ids' => 'loan_ids parameter is required (comma-separated)']
                );
            }

            // Parse loan IDs
            $loanIdString = $queryParams['loan_ids'];
            $loanIds = array_filter(
                array_map('intval', explode(',', $loanIdString)),
                fn($id) => $id > 0
            );

            if (empty($loanIds)) {
                throw new ValidationException(
                    'Invalid loan IDs',
                    ['loan_ids' => 'loan_ids must be comma-separated positive integers']
                );
            }

            // Generate timeline
            $timeline = $this->analysisService->getDebtPayoffTimeline($loanIds);

            return ApiResponse::success(
                $timeline,
                'Debt payoff timeline generated successfully'
            );
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate timeline: ' . $e->getMessage());
        }
    }
}
