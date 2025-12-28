<?php
namespace Ksfraser\Amortizations\Services;

use DateTime;
use InvalidArgumentException;

/**
 * CacheManager: Manages caching for expensive service operations
 * 
 * Provides TTL-based caching, manual invalidation, and cache statistics
 * to optimize performance across all amortization services.
 */
class CacheManager {
    /**
     * @var array
     */
    private $cache = [];
    /**
     * @var array
     */
    private $metadata = [];
    /**
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'ttl_expirations' => 0
    ];
    /**
     * @var int
     */
    private $defaultTTL = 3600;

    /**
     * Set cache value with TTL
     * 
     * @param string $key Cache key
     * @param mixed $value Cache value
     * @param int $ttl Time to live in seconds (default: 1 hour)
     */
    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     */
    public function set(string $key, $value, $ttl = null): void {
        if (empty($key)) {
            throw new InvalidArgumentException("Cache key cannot be empty");
        }

        $ttl = $ttl ?? $this->defaultTTL;
        
        $this->cache[$key] = $value;
        $this->metadata[$key] = [
            'created_at' => time(),
            'ttl' => $ttl,
            'expires_at' => time() + $ttl,
            'hits' => 0
        ];
        
        $this->stats['sets']++;
    }

    /**
     * Get cache value if not expired
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if expired/missing
     */
    /**
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key) {
        if (!$this->has($key)) {
            $this->stats['misses']++;
            return null;
        }

        if ($this->isExpired($key)) {
            $this->delete($key);
            $this->stats['misses']++;
            $this->stats['ttl_expirations']++;
            return null;
        }

        $this->metadata[$key]['hits']++;
        $this->stats['hits']++;
        
        return $this->cache[$key];
    }

    /**
     * Check if key exists and is not expired
     */
    public function has(string $key): bool {
        return isset($this->cache[$key]) && !$this->isExpired($key);
    }

    /**
     * Check if cache entry has expired
     */
    private function isExpired(string $key): bool {
        if (!isset($this->metadata[$key])) {
            return true;
        }
        
        $expiresAt = $this->metadata[$key]['expires_at'];
        return time() > $expiresAt;
    }

    /**
     * Delete cache entry
     */
    public function delete(string $key): bool {
        if (!isset($this->cache[$key])) {
            return false;
        }

        unset($this->cache[$key]);
        unset($this->metadata[$key]);
        $this->stats['deletes']++;
        
        return true;
    }

    /**
     * Delete multiple cache entries by pattern
     * 
     * @param string $pattern Regex pattern for keys to delete
     * @return int Number of keys deleted
     */
    public function deleteByPattern(string $pattern): int {
        $deleted = 0;
        foreach (array_keys($this->cache) as $key) {
            if (preg_match($pattern, $key)) {
                $this->delete($key);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Clear all cache
     */
    public function clear(): void {
        $this->cache = [];
        $this->metadata = [];
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $hits = $this->stats['hits'];
        $misses = $this->stats['misses'];
        $total = $hits + $misses;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0.0,
            'total_requests' => $total,
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'ttl_expirations' => $this->stats['ttl_expirations'],
            'current_size' => count($this->cache),
            'memory_usage_bytes' => strlen(serialize($this->cache))
        ];
    }

    /**
     * Get metadata for a cache entry
     */
    public function getMetadata(string $key): ?array {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Get all cache keys
     */
    public function getKeys(): array {
        return array_keys($this->cache);
    }

    /**
     * Get cache size in bytes
     */
    public function getSize(): int {
        return strlen(serialize($this->cache));
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'ttl_expirations' => 0
        ];
    }

    /**
     * Warm cache with pre-computed values
     * 
     * @param array $data Key-value pairs to pre-populate cache
     * @param int $ttl Time to live for all entries
     */
    public function warm(array $data, int $ttl = null): int {
        $count = 0;
        foreach ($data as $key => $value) {
            $this->set((string)$key, $value, $ttl);
            $count++;
        }
        return $count;
    }

    /**
     * Get all entries expiring soon (within threshold)
     */
    public function getExpiringEntries(int $thresholdSeconds = 300): array {
        $expiring = [];
        $now = time();

        foreach ($this->metadata as $key => $meta) {
            $timeRemaining = $meta['expires_at'] - $now;
            if ($timeRemaining > 0 && $timeRemaining <= $thresholdSeconds) {
                $expiring[$key] = [
                    'expires_in_seconds' => $timeRemaining,
                    'hits' => $meta['hits']
                ];
            }
        }

        return $expiring;
    }

    /**
     * Purge expired entries
     */
    public function purgeExpired(): int {
        $deleted = 0;
        foreach (array_keys($this->cache) as $key) {
            if ($this->isExpired($key)) {
                $this->delete($key);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Set default TTL for new entries
     */
    public function setDefaultTTL(int $ttl): void {
        if ($ttl < 1) {
            throw new InvalidArgumentException("TTL must be at least 1 second");
        }
        $this->defaultTTL = $ttl;
    }

    /**
     * Get default TTL
     */
    public function getDefaultTTL(): int {
        return $this->defaultTTL;
    }
}
