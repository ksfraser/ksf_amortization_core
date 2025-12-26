<?php
namespace Ksfraser\Amortizations\Api;

/**
 * LoanAnalysisRequest: Request DTO for loan analysis API
 */
class LoanAnalysisRequest {
    public float $principal;
    public float $annualRate;
    public int $months;
    public float $monthlyIncome;
    public float $creditScore = 750;
    public float $otherMonthlyDebts = 0;
    public float $employmentYears = 5;

    public static function fromArray(array $data): self {
        $req = new self();
        $req->principal = (float)($data['principal'] ?? 0);
        $req->annualRate = (float)($data['annual_rate'] ?? 0);
        $req->months = (int)($data['months'] ?? 360);
        $req->monthlyIncome = (float)($data['monthly_income'] ?? 0);
        $req->creditScore = (float)($data['credit_score'] ?? 750);
        $req->otherMonthlyDebts = (float)($data['other_monthly_debts'] ?? 0);
        $req->employmentYears = (float)($data['employment_years'] ?? 5);
        
        return $req;
    }

    public function validate(): array {
        $errors = [];
        if ($this->principal <= 0) $errors[] = 'principal must be greater than 0';
        if ($this->annualRate < 0 || $this->annualRate > 1) $errors[] = 'annual_rate must be between 0 and 1';
        if ($this->months <= 0) $errors[] = 'months must be greater than 0';
        if ($this->monthlyIncome <= 0) $errors[] = 'monthly_income must be greater than 0';
        if ($this->creditScore < 300 || $this->creditScore > 850) $errors[] = 'credit_score must be between 300 and 850';
        
        return $errors;
    }
}

/**
 * PortfolioRequest: Request DTO for portfolio operations
 */
class PortfolioRequest {
    public array $loanIds;
    public string $name = '';
    public int $cacheTTL = 1800;

    public static function fromArray(array $data): self {
        $req = new self();
        $req->loanIds = $data['loan_ids'] ?? [];
        $req->name = (string)($data['name'] ?? '');
        $req->cacheTTL = (int)($data['cache_ttl'] ?? 1800);
        
        return $req;
    }

    public function validate(): array {
        $errors = [];
        if (empty($this->loanIds)) $errors[] = 'loan_ids cannot be empty';
        
        return $errors;
    }
}

/**
 * ReportRequest: Request DTO for report generation
 */
class ReportRequest {
    public float $principal;
    public float $annualRate;
    public int $months;
    public string $format = 'json'; // json, csv, xml, html
    public bool $includeCharts = false;

    public static function fromArray(array $data): self {
        $req = new self();
        $req->principal = (float)($data['principal'] ?? 0);
        $req->annualRate = (float)($data['annual_rate'] ?? 0);
        $req->months = (int)($data['months'] ?? 360);
        $req->format = in_array($data['format'] ?? 'json', ['json', 'csv', 'xml', 'html']) 
            ? $data['format'] 
            : 'json';
        $req->includeCharts = (bool)($data['include_charts'] ?? false);
        
        return $req;
    }

    public function validate(): array {
        $errors = [];
        if ($this->principal <= 0) $errors[] = 'principal must be greater than 0';
        if ($this->annualRate < 0 || $this->annualRate > 1) $errors[] = 'annual_rate must be between 0 and 1';
        if ($this->months <= 0) $errors[] = 'months must be greater than 0';
        if (!in_array($this->format, ['json', 'csv', 'xml', 'html'])) {
            $errors[] = 'format must be one of: json, csv, xml, html';
        }
        
        return $errors;
    }
}

/**
 * OriginationRequest: Request DTO for loan origination
 */
class OriginationRequest {
    public string $applicantName;
    public float $requestedAmount;
    public string $purpose;
    public float $principal;
    public float $annualRate;
    public int $months;

    public static function fromArray(array $data): self {
        $req = new self();
        $req->applicantName = (string)($data['applicant_name'] ?? 'Unknown');
        $req->requestedAmount = (float)($data['requested_amount'] ?? 0);
        $req->purpose = (string)($data['purpose'] ?? '');
        $req->principal = (float)($data['principal'] ?? 0);
        $req->annualRate = (float)($data['annual_rate'] ?? 0);
        $req->months = (int)($data['months'] ?? 360);
        
        return $req;
    }

    public function validate(): array {
        $errors = [];
        if (empty($this->applicantName)) $errors[] = 'applicant_name is required';
        if ($this->requestedAmount <= 0) $errors[] = 'requested_amount must be greater than 0';
        if (empty($this->purpose)) $errors[] = 'purpose is required';
        if ($this->principal <= 0) $errors[] = 'principal must be greater than 0';
        if ($this->annualRate < 0 || $this->annualRate > 1) $errors[] = 'annual_rate must be between 0 and 1';
        if ($this->months <= 0) $errors[] = 'months must be greater than 0';
        
        return $errors;
    }
}

/**
 * MarketRequest: Request DTO for market analysis
 */
class MarketRequest {
    public float $currentRate;
    public float $margin = 0.002;
    public array $competitorRates = [];
    public string $marketSegment = 'mortgage_30_year';

    public static function fromArray(array $data): self {
        $req = new self();
        $req->currentRate = (float)($data['current_rate'] ?? 0.05);
        $req->margin = (float)($data['margin'] ?? 0.002);
        $req->competitorRates = $data['competitor_rates'] ?? [];
        $req->marketSegment = (string)($data['market_segment'] ?? 'mortgage_30_year');
        
        return $req;
    }

    public function validate(): array {
        $errors = [];
        if ($this->currentRate < 0 || $this->currentRate > 1) $errors[] = 'current_rate must be between 0 and 1';
        if ($this->margin < 0 || $this->margin > 1) $errors[] = 'margin must be between 0 and 1';
        if (!is_array($this->competitorRates)) $errors[] = 'competitor_rates must be an array';
        
        return $errors;
    }
}
