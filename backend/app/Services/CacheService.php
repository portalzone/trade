<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Cache product data
     */
    public function cacheProduct(int $productId, array $data, int $ttl = 3600): void
    {
        $key = "product:{$productId}";
        Cache::put($key, $data, $ttl);
    }

    /**
     * Get cached product
     */
    public function getCachedProduct(int $productId): ?array
    {
        $key = "product:{$productId}";
        return Cache::get($key);
    }

    /**
     * Invalidate product cache
     */
    public function invalidateProduct(int $productId): void
    {
        $key = "product:{$productId}";
        Cache::forget($key);
    }

    /**
     * Cache search results
     */
    public function cacheSearchResults(string $query, array $filters, array $results, int $ttl = 600): void
    {
        $key = $this->getSearchCacheKey($query, $filters);
        Cache::put($key, $results, $ttl);
    }

    /**
     * Get cached search results
     */
    public function getCachedSearchResults(string $query, array $filters): ?array
    {
        $key = $this->getSearchCacheKey($query, $filters);
        return Cache::get($key);
    }

    /**
     * Generate search cache key
     */
    protected function getSearchCacheKey(string $query, array $filters): string
    {
        $filterString = md5(json_encode($filters));
        return "search:{$query}:{$filterString}";
    }

    /**
     * Cache user tier limits
     */
    public function cacheUserLimits(int $userId, array $limits, int $ttl = 86400): void
    {
        $key = "user_limits:{$userId}";
        Cache::put($key, $limits, $ttl);
    }

    /**
     * Get cached user limits
     */
    public function getCachedUserLimits(int $userId): ?array
    {
        $key = "user_limits:{$userId}";
        return Cache::get($key);
    }

    /**
     * Invalidate user limits cache
     */
    public function invalidateUserLimits(int $userId): void
    {
        $key = "user_limits:{$userId}";
        Cache::forget($key);
    }

    /**
     * Cache monitoring rules
     */
    public function cacheMonitoringRules(array $rules, int $ttl = 3600): void
    {
        Cache::put('monitoring:rules', $rules, $ttl);
    }

    /**
     * Get cached monitoring rules
     */
    public function getCachedMonitoringRules(): ?array
    {
        return Cache::get('monitoring:rules');
    }

    /**
     * Clear all cache
     */
    public function clearAll(): void
    {
        Cache::flush();
        Log::info('All cache cleared');
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        // Redis-specific stats (if using Redis)
        try {
            $redis = Cache::getRedis();
            $info = $redis->info();

            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Cache statistics not available'];
        }
    }

    /**
     * Calculate cache hit rate
     */
    protected function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return 0;
        }

        return round(($hits / $total) * 100, 2);
    }
}
