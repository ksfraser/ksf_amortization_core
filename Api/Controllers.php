<?php
namespace Ksfraser\Amortizations\Api;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Services\LoanAnalysisService;

/**
 * LoanAnalysisController: API controller for loan analysis operations
 */
class LoanAnalysisController {
    private LoanAnalysisService $service;

    public function __construct(LoanAnalysisService $service = null) {
        $this->service = $service ?? new LoanAnalysisService();
    }

    /**
     * POST /api/v1/loans/analyze
     * Analyze and qualify a loan application
     */
    public function analyze(array $requestData): ApiResponse {
        try {
            $request = LoanAnalysisRequest::fromArray($requestData);
            $errors = $request->validate();

            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            // Create loan from request
            $loan = new Loan();
            $loan->setPrincipal($request->principal)
                 ->setAnnualRate($request->annualRate)
                 ->setMonths($request->months);

            // Perform analysis
            $qualification = $this->service->generateLoanQualificationReport(
                $loan,
                $request->monthlyIncome,
                $request->creditScore
            );

            $riskAssessment = $this->service->assessLoanRisk($loan, $request->creditScore);
            $creditworthiness = $this->service->calculateCreditworthinessScore(
                $loan,
                $request->creditScore,
                $this->service->calculateDebtToIncomeRatio(
                    $loan,
                    $request->monthlyIncome,
                    $request->otherMonthlyDebts
                ),
                $request->employmentYears
            );

            $ltv = $this->service->calculateLoanToValueRatio($loan, $request->principal);
            $dti = $this->service->calculateDebtToIncomeRatio(
                $loan,
                $request->monthlyIncome,
                $request->otherMonthlyDebts
            );

            $maxBorrow = $this->service->calculateMaxBorrowAmount(
                $request->monthlyIncome,
                $request->otherMonthlyDebts,
                $request->annualRate,
                $request->months
            );

            $response = LoanAnalysisResponse::create(
                $qualification['recommendation'] === 'qualified',
                $qualification['recommendation'] ?? 'unknown',
                $ltv,
                $dti,
                $creditworthiness,
                $riskAssessment,
                $maxBorrow,
                $qualification['is_affordable'] ?? false
            );

            return ApiResponse::success($response->toArray(), 'Loan analysis completed');
        } catch (\Exception $e) {
            return ApiResponse::error('Analysis failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/v1/loans/rates
     * Get current interest rates
     */
    public function getRates(): ApiResponse {
        try {
            $rates = [
                'prime_rate' => 0.05,
                'average_mortgage_30' => 0.067,
                'average_mortgage_15' => 0.062,
                'auto_loan_rate' => 0.055,
                'personal_loan_rate' => 0.085
            ];

            return ApiResponse::success($rates, 'Current rates retrieved');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve rates', null, 500);
        }
    }

    /**
     * POST /api/v1/loans/compare
     * Compare multiple loan options
     */
    public function compare(array $requestData): ApiResponse {
        try {
            if (empty($requestData['loans'])) {
                return ApiResponse::validationError(['loans' => 'At least one loan required']);
            }

            $comparisons = [];
            foreach ($requestData['loans'] as $loanData) {
                $loan = new Loan();
                $loan->setPrincipal((float)$loanData['principal'])
                     ->setAnnualRate((float)$loanData['annual_rate'])
                     ->setMonths((int)$loanData['months']);

                $comparison = $this->service->compareLoanOptions(
                    $loan,
                    (float)$requestData['monthly_income'] ?? 5000,
                    (int)$requestData['credit_score'] ?? 750
                );

                $comparisons[] = $comparison;
            }

            return ApiResponse::success(['comparisons' => $comparisons], 'Loans compared');
        } catch (\Exception $e) {
            return ApiResponse::error('Comparison failed: ' . $e->getMessage(), null, 500);
        }
    }
}

/**
 * PortfolioController: API controller for portfolio operations
 */
class PortfolioController {
    private $portfolioService;

    public function __construct($portfolioService = null) {
        $this->portfolioService = $portfolioService;
    }

    /**
     * POST /api/v1/portfolios
     * Create or analyze a portfolio
     */
    public function analyze(array $requestData): ApiResponse {
        try {
            $request = PortfolioRequest::fromArray($requestData);
            $errors = $request->validate();

            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            if (!$this->portfolioService) {
                return ApiResponse::error('Portfolio service not configured', null, 500);
            }

            // Note: In production, would load loans from database by IDs
            // For now, return success structure
            $portfolio = [
                'portfolio_id' => md5($request->name . time()),
                'name' => $request->name,
                'total_loans' => count($request->loanIds),
                'loan_ids' => $request->loanIds
            ];

            $response = PortfolioResponse::create(
                $portfolio,
                ['portfolio_risk_level' => 'medium', 'average_risk_score' => 50],
                0.055,
                ['profitability_ratio' => 0.042]
            );

            return ApiResponse::success($response->toArray(), 'Portfolio analyzed');
        } catch (\Exception $e) {
            return ApiResponse::error('Portfolio analysis failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/v1/portfolios/{id}
     * Retrieve portfolio details
     */
    public function retrieve(string $portfolioId): ApiResponse {
        try {
            if (empty($portfolioId)) {
                return ApiResponse::validationError(['portfolio_id' => 'Portfolio ID required']);
            }

            $portfolio = [
                'portfolio_id' => $portfolioId,
                'name' => 'Portfolio ' . substr($portfolioId, 0, 8),
                'total_loans' => 5,
                'created_at' => date('c')
            ];

            return ApiResponse::success($portfolio, 'Portfolio retrieved');
        } catch (\Exception $e) {
            return ApiResponse::error('Portfolio retrieval failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/v1/portfolios/{id}/yield
     * Get portfolio yield metrics
     */
    public function getYield(string $portfolioId): ApiResponse {
        try {
            if (empty($portfolioId)) {
                return ApiResponse::notFound('Portfolio not found');
            }

            $yieldData = [
                'portfolio_id' => $portfolioId,
                'total_yield' => 0.055,
                'weighted_average_rate' => 0.058,
                'current_yield' => 0.052,
                'ytd_return' => 0.048
            ];

            return ApiResponse::success($yieldData, 'Portfolio yield retrieved');
        } catch (\Exception $e) {
            return ApiResponse::error('Yield retrieval failed: ' . $e->getMessage(), null, 500);
        }
    }
}

/**
 * ReportingController: API controller for report generation
 */
class ReportingController {
    private $reportingService;

    public function __construct($reportingService = null) {
        $this->reportingService = $reportingService;
    }

    /**
     * POST /api/v1/reports
     * Generate financial report
     */
    public function generate(array $requestData): ApiResponse {
        try {
            $request = ReportRequest::fromArray($requestData);
            $errors = $request->validate();

            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            // Generate report based on format
            $content = match ($request->format) {
                'csv' => $this->generateCsvReport($request),
                'xml' => $this->generateXmlReport($request),
                'html' => $this->generateHtmlReport($request),
                default => $this->generateJsonReport($request)
            };

            $response = ReportResponse::create(
                $request->format,
                $content,
                [
                    'generated_at' => date('c'),
                    'includes_charts' => $request->includeCharts
                ]
            );

            return ApiResponse::success($response->toArray(), 'Report generated');
        } catch (\Exception $e) {
            return ApiResponse::error('Report generation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v1/reports/export
     * Export report in specified format
     */
    public function export(array $requestData): ApiResponse {
        try {
            $format = $requestData['format'] ?? 'json';
            
            if (!in_array($format, ['json', 'csv', 'xml', 'html', 'pdf'])) {
                return ApiResponse::validationError(['format' => 'Invalid export format']);
            }

            $response = ReportResponse::create(
                $format,
                'export_placeholder_' . $format,
                ['format' => $format, 'exported_at' => date('c')]
            );

            return ApiResponse::success($response->toArray(), 'Report exported');
        } catch (\Exception $e) {
            return ApiResponse::error('Export failed: ' . $e->getMessage(), null, 500);
        }
    }

    private function generateJsonReport(ReportRequest $request): array {
        return [
            'principal' => $request->principal,
            'annual_rate' => $request->annualRate,
            'months' => $request->months,
            'monthly_payment' => round($this->calculateMonthlyPayment($request), 2)
        ];
    }

    private function generateCsvReport(ReportRequest $request): string {
        $payment = round($this->calculateMonthlyPayment($request), 2);
        return "Principal,Annual Rate,Months,Monthly Payment\n{$request->principal},{$request->annualRate},{$request->months},{$payment}\n";
    }

    private function generateXmlReport(ReportRequest $request): string {
        $payment = round($this->calculateMonthlyPayment($request), 2);
        return "<?xml version=\"1.0\"?><report><principal>{$request->principal}</principal><rate>{$request->annualRate}</rate><months>{$request->months}</months><payment>{$payment}</payment></report>";
    }

    private function generateHtmlReport(ReportRequest $request): string {
        $payment = round($this->calculateMonthlyPayment($request), 2);
        return "<html><body><h1>Loan Report</h1><p>Principal: {$request->principal}</p><p>Rate: {$request->annualRate}</p><p>Months: {$request->months}</p><p>Monthly Payment: {$payment}</p></body></html>";
    }

    private function calculateMonthlyPayment(ReportRequest $request): float {
        $monthlyRate = $request->annualRate / 12;
        if ($monthlyRate > 0) {
            return $request->principal * ($monthlyRate * pow(1 + $monthlyRate, $request->months)) 
                   / (pow(1 + $monthlyRate, $request->months) - 1);
        }
        return $request->principal / $request->months;
    }
}

/**
 * OriginationController: API controller for loan origination
 */
class OriginationController {
    private $originationService;

    public function __construct($originationService = null) {
        $this->originationService = $originationService;
    }

    /**
     * POST /api/v1/originations/applications
     * Create loan application
     */
    public function createApplication(array $requestData): ApiResponse {
        try {
            $request = OriginationRequest::fromArray($requestData);
            $errors = $request->validate();

            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            $applicationId = 'APP-' . strtoupper(substr(md5(time()), 0, 8));

            $response = OriginationResponse::create(
                $applicationId,
                'pending_review',
                $request->requestedAmount,
                $request->annualRate,
                ['applicant' => $request->applicantName, 'purpose' => $request->purpose],
                []
            );

            return ApiResponse::success($response->toArray(), 'Application created', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Application creation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v1/originations/{id}/approve
     * Approve loan application
     */
    public function approve(string $applicationId, array $requestData): ApiResponse {
        try {
            if (empty($applicationId)) {
                return ApiResponse::notFound('Application not found');
            }

            $response = OriginationResponse::create(
                $applicationId,
                'approved',
                (float)($requestData['approved_amount'] ?? 0),
                (float)($requestData['approved_rate'] ?? 0),
                ['status' => 'approved', 'approved_at' => date('c')],
                []
            );

            return ApiResponse::success($response->toArray(), 'Application approved');
        } catch (\Exception $e) {
            return ApiResponse::error('Approval failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v1/originations/{id}/reject
     * Reject loan application
     */
    public function reject(string $applicationId, array $requestData): ApiResponse {
        try {
            if (empty($applicationId)) {
                return ApiResponse::notFound('Application not found');
            }

            $reason = $requestData['reason'] ?? 'Application does not meet lending criteria';

            return ApiResponse::success(
                [
                    'application_id' => $applicationId,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'rejected_at' => date('c')
                ],
                'Application rejected'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Rejection failed: ' . $e->getMessage(), null, 500);
        }
    }
}

/**
 * MarketController: API controller for market analysis
 */
class MarketController {
    private $marketService;

    public function __construct($marketService = null) {
        $this->marketService = $marketService;
    }

    /**
     * GET /api/v1/market/rates
     * Get current market rates
     */
    public function getRates(): ApiResponse {
        try {
            $rates = [
                'mortgage_30_year' => 0.067,
                'mortgage_15_year' => 0.062,
                'auto_loan' => 0.055,
                'personal_loan' => 0.085,
                'home_equity' => 0.082
            ];

            return ApiResponse::success($rates, 'Market rates retrieved');
        } catch (\Exception $e) {
            return ApiResponse::error('Rate retrieval failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v1/market/forecast
     * Get market rate forecast
     */
    public function forecast(array $requestData): ApiResponse {
        try {
            $request = MarketRequest::fromArray($requestData);
            $errors = $request->validate();

            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            $forecast = [
                'current_rate' => $request->currentRate,
                'forecast_months' => 12,
                'forecasted_rates' => [
                    $request->currentRate,
                    $request->currentRate + 0.001,
                    $request->currentRate + 0.002
                ],
                'trend' => 'increasing'
            ];

            $response = MarketResponse::create(
                ['current' => $request->currentRate],
                ['vs_average' => 'below_average'],
                $forecast,
                ['action' => 'consider_locking_rate']
            );

            return ApiResponse::success($response->toArray(), 'Forecast generated');
        } catch (\Exception $e) {
            return ApiResponse::error('Forecast generation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v1/market/compare
     * Compare rates with competitors
     */
    public function compareRates(array $requestData): ApiResponse {
        try {
            $request = MarketRequest::fromArray($requestData);
            
            $comparison = [
                'your_rate' => $request->currentRate,
                'market_average' => 0.060,
                'competitor_rates' => $request->competitorRates,
                'competitiveness_rank' => 3,
                'recommendation' => 'competitive'
            ];

            return ApiResponse::success($comparison, 'Rate comparison completed');
        } catch (\Exception $e) {
            return ApiResponse::error('Comparison failed: ' . $e->getMessage(), null, 500);
        }
    }
}
