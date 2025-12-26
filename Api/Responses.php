<?php
namespace Ksfraser\Amortizations\Api;

/**
 * ApiResponse: Standard API response wrapper
 */
class ApiResponse {
    public bool $success;
    public string $message = '';
    public mixed $data = null;
    public ?array $errors = null;
    public int $statusCode = 200;

    public static function success(mixed $data, string $message = 'Success', int $statusCode = 200): self {
        $response = new self();
        $response->success = true;
        $response->data = $data;
        $response->message = $message;
        $response->statusCode = $statusCode;
        
        return $response;
    }

    public static function error(string $message, ?array $errors = null, int $statusCode = 400): self {
        $response = new self();
        $response->success = false;
        $response->message = $message;
        $response->errors = $errors;
        $response->statusCode = $statusCode;
        
        return $response;
    }

    public static function validationError(array $errors, int $statusCode = 422): self {
        return self::error('Validation failed', $errors, $statusCode);
    }

    public static function notFound(string $message = 'Resource not found'): self {
        return self::error($message, null, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self {
        return self::error($message, null, 401);
    }

    public static function tooManyRequests(string $message = 'Too many requests'): self {
        return self::error($message, null, 429);
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'errors' => $this->errors,
            'timestamp' => date('c')
        ];
    }

    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * LoanAnalysisResponse: Response for loan analysis
 */
class LoanAnalysisResponse {
    public bool $qualified;
    public string $recommendation;
    public float $loanToValue;
    public float $debtToIncome;
    public array $creditworthiness;
    public array $riskAssessment;
    public float $maxBorrowAmount;
    public bool $isAffordable;

    public static function create(
        bool $qualified,
        string $recommendation,
        float $ltv,
        float $dti,
        array $creditworthiness,
        array $riskAssessment,
        float $maxBorrow,
        bool $affordable
    ): self {
        $resp = new self();
        $resp->qualified = $qualified;
        $resp->recommendation = $recommendation;
        $resp->loanToValue = $ltv;
        $resp->debtToIncome = $dti;
        $resp->creditworthiness = $creditworthiness;
        $resp->riskAssessment = $riskAssessment;
        $resp->maxBorrowAmount = $maxBorrow;
        $resp->isAffordable = $affordable;
        
        return $resp;
    }

    public function toArray(): array {
        return [
            'qualified' => $this->qualified,
            'recommendation' => $this->recommendation,
            'loan_to_value' => $this->loanToValue,
            'debt_to_income' => $this->debtToIncome,
            'creditworthiness' => $this->creditworthiness,
            'risk_assessment' => $this->riskAssessment,
            'max_borrow_amount' => $this->maxBorrowAmount,
            'is_affordable' => $this->isAffordable
        ];
    }
}

/**
 * PortfolioResponse: Response for portfolio operations
 */
class PortfolioResponse {
    public array $portfolio;
    public array $riskProfile;
    public float $yield;
    public array $profitability;

    public static function create(
        array $portfolio,
        array $riskProfile,
        float $yield,
        array $profitability
    ): self {
        $resp = new self();
        $resp->portfolio = $portfolio;
        $resp->riskProfile = $riskProfile;
        $resp->yield = $yield;
        $resp->profitability = $profitability;
        
        return $resp;
    }

    public function toArray(): array {
        return [
            'portfolio' => $this->portfolio,
            'risk_profile' => $this->riskProfile,
            'yield' => $this->yield,
            'profitability' => $this->profitability
        ];
    }
}

/**
 * ReportResponse: Response for report generation
 */
class ReportResponse {
    public string $format;
    public mixed $content;
    public array $metadata;

    public static function create(string $format, mixed $content, array $metadata = []): self {
        $resp = new self();
        $resp->format = $format;
        $resp->content = $content;
        $resp->metadata = $metadata;
        
        return $resp;
    }

    public function toArray(): array {
        $contentKey = $this->format === 'json' ? 'data' : 'content';
        
        return [
            'format' => $this->format,
            $contentKey => $this->content,
            'metadata' => $this->metadata
        ];
    }
}

/**
 * OriginationResponse: Response for loan origination
 */
class OriginationResponse {
    public string $applicationId;
    public string $status;
    public float $approvedAmount;
    public float $approvedRate;
    public array $offerDetails;
    public array $documents;

    public static function create(
        string $applicationId,
        string $status,
        float $amount,
        float $rate,
        array $offerDetails = [],
        array $documents = []
    ): self {
        $resp = new self();
        $resp->applicationId = $applicationId;
        $resp->status = $status;
        $resp->approvedAmount = $amount;
        $resp->approvedRate = $rate;
        $resp->offerDetails = $offerDetails;
        $resp->documents = $documents;
        
        return $resp;
    }

    public function toArray(): array {
        return [
            'application_id' => $this->applicationId,
            'status' => $this->status,
            'approved_amount' => $this->approvedAmount,
            'approved_rate' => $this->approvedRate,
            'offer_details' => $this->offerDetails,
            'documents' => $this->documents
        ];
    }
}

/**
 * MarketResponse: Response for market analysis
 */
class MarketResponse {
    public array $marketRates;
    public array $comparison;
    public array $forecast;
    public array $recommendations;

    public static function create(
        array $marketRates,
        array $comparison,
        array $forecast,
        array $recommendations = []
    ): self {
        $resp = new self();
        $resp->marketRates = $marketRates;
        $resp->comparison = $comparison;
        $resp->forecast = $forecast;
        $resp->recommendations = $recommendations;
        
        return $resp;
    }

    public function toArray(): array {
        return [
            'market_rates' => $this->marketRates,
            'comparison' => $this->comparison,
            'forecast' => $this->forecast,
            'recommendations' => $this->recommendations
        ];
    }
}
