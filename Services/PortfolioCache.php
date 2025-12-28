<?php
namespace Ksfraser\Amortizations\Services;

use Ksfraser\Amortizations\Models\Loan;

/**
 * PortfolioCache: Caches expensive portfolio calculations
 * 
 * Integrates CacheManager with PortfolioManagementService to optimize
 * frequently-accessed portfolio metrics and aggregations.
 */
class PortfolioCache {
    /**
     * @var PortfolioManagementService
     */
    private $portfolioService;
    /**
     * @var CacheManager
     */
    private $cache;
    /**
     * @var int
     */
    private $defaultTTL = 1800; // 30 minutes

    public function __construct(
        PortfolioManagementService $portfolioService = null,
        CacheManager $cache = null
    ) {
        $this->portfolioService = $portfolioService ?? new PortfolioManagementService();
        $this->cache = $cache ?? new CacheManager();
        $this->cache->setDefaultTTL($this->defaultTTL);
    }

    /**
     * Get cached portfolio report or compute fresh
     */
    public function getCachedPortfolioReport(array $loans, int $cacheTTL = null): array {
        $cacheKey = $this->generatePortfolioKey('report', $loans);
        $cacheTTL = $cacheTTL ?? $this->defaultTTL;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $report = $this->portfolioService->exportPortfolioReport($loans);
        $this->cache->set($cacheKey, $report, $cacheTTL);

        return $report;
    }

    /**
     * Get cached portfolio risk profile
     */
    public function getCachedRiskProfile(array $loans, int $cacheTTL = null): array {
        $cacheKey = $this->generatePortfolioKey('risk_profile', $loans);
        $cacheTTL = $cacheTTL ?? $this->defaultTTL;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $profile = $this->portfolioService->getPortfolioRiskProfile($loans);
        $this->cache->set($cacheKey, $profile, $cacheTTL);

        return $profile;
    }

    /**
     * Get cached portfolio yield
     */
    public function getCachedYield(array $loans, int $cacheTTL = null): float {
        $cacheKey = $this->generatePortfolioKey('yield', $loans);
        $cacheTTL = $cacheTTL ?? $this->defaultTTL;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $yield = $this->portfolioService->calculatePortfolioYield($loans);
        $this->cache->set($cacheKey, $yield, $cacheTTL);

        return $yield;
    }

    /**
     * Get cached profitability metrics
     */
    public function getCachedProfitability(array $loans, int $cacheTTL = null): array {
        $cacheKey = $this->generatePortfolioKey('profitability', $loans);
        $cacheTTL = $cacheTTL ?? $this->defaultTTL;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $profitability = $this->portfolioService->calculateProfitability($loans);
        $this->cache->set($cacheKey, $profitability, $cacheTTL);

        return $profitability;
    }

    /**
     * Get cached diversification metrics
     */
    public function getCachedDiversification(array $loans, int $cacheTTL = null): array {
        $cacheKey = $this->generatePortfolioKey('diversification', $loans);
        $cacheTTL = $cacheTTL ?? $this->defaultTTL;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $diversification = $this->portfolioService->getLoanDiversification($loans);
        $this->cache->set($cacheKey, $diversification, $cacheTTL);

        return $diversification;
    }

    /**
     * Get cached ranking of loans
     */
    public function getCachedRanking(array $loans, int $cacheTTL = null): array {
        $cacheKey = $this->generatePortfolioKey('ranking', $loans);
        $cacheTTL = $cacheTTL ?? $this->defaultTTL;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $ranking = $this->portfolioService->rankLoansByPerformance($loans);
        $this->cache->set($cacheKey, $ranking, $cacheTTL);

        return $ranking;
    }

    /**
     * Invalidate all portfolio-related caches
     */
    public function invalidatePortfolioCache(string $pattern = null): int {
        if ($pattern === null) {
            $pattern = '/^portfolio_/';
        }

        return $this->cache->deleteByPattern($pattern);
    }

    /**
     * Invalidate specific portfolio cache by loan IDs
     */
    public function invalidateForLoans(array $loans): int {
        $loanIds = array_map(function($l) { return $l->getId(); }, $loans);
        $idString = implode('_', $loanIds);
        
        return $this->cache->deleteByPattern("/.*{$idString}.*/");
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        return $this->cache->getStats();
    }

    /**
     * Warm cache with common portfolio calculations
     */
    public function warmCache(array $loans, int $ttl = null): int {
        $ttl = $ttl ?? $this->defaultTTL;
        $warmed = 0;

        // Cache report
        $reportKey = $this->generatePortfolioKey('report', $loans);
        $report = $this->portfolioService->exportPortfolioReport($loans);
        $this->cache->set($reportKey, $report, $ttl);
        $warmed++;

        // Cache risk profile
        $riskKey = $this->generatePortfolioKey('risk_profile', $loans);
        $risk = $this->portfolioService->getPortfolioRiskProfile($loans);
        $this->cache->set($riskKey, $risk, $ttl);
        $warmed++;

        // Cache yield
        $yieldKey = $this->generatePortfolioKey('yield', $loans);
        $yield = $this->portfolioService->calculatePortfolioYield($loans);
        $this->cache->set($yieldKey, $yield, $ttl);
        $warmed++;

        // Cache profitability
        $profitKey = $this->generatePortfolioKey('profitability', $loans);
        $profit = $this->portfolioService->calculateProfitability($loans);
        $this->cache->set($profitKey, $profit, $ttl);
        $warmed++;

        return $warmed;
    }

    /**
     * Get underlying cache manager
     */
    public function getCacheManager(): CacheManager {
        return $this->cache;
    }

    /**
     * Generate consistent cache key for portfolio calculations
     */
    private function generatePortfolioKey(string $metric, array $loans): string {
        $loanIds = array_map(function($l) { return $l->getId(); }, $loans);
        sort($loanIds);
        $idHash = md5(implode(':', $loanIds));

        return "portfolio_{$metric}_{$idHash}";
    }

    /**
     * Set default TTL for cache entries
     */
    public function setDefaultTTL(int $ttl): void {
        $this->defaultTTL = $ttl;
        $this->cache->setDefaultTTL($ttl);
    }

    /**
     * Get current cache size in bytes
     */
    public function getCacheSize(): int {
        return $this->cache->getSize();
    }

    /**
     * Clear all caches
     */
    public function clearCache(): void {
        $this->cache->clear();
    }
}
