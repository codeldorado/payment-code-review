<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing application caching strategies
 */
class CacheService
{
    private const DEFAULT_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'app_cache_';

    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Get cached data or execute callback to generate it
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cacheKey = $this->generateKey($key);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $this->logger->debug('Cache hit', ['key' => $key]);
            return $cacheItem->get();
        }

        $this->logger->debug('Cache miss, generating data', ['key' => $key]);
        
        $startTime = microtime(true);
        $data = $callback();
        $duration = microtime(true) - $startTime;

        $cacheItem->set($data);
        $cacheItem->expiresAfter($ttl);
        $this->cache->save($cacheItem);

        $this->logger->info('Data cached', [
            'key' => $key,
            'ttl' => $ttl,
            'generation_time' => $duration
        ]);

        return $data;
    }

    /**
     * Cache subscription statistics
     */
    public function cacheSubscriptionStats(callable $statsCallback): array
    {
        return $this->remember(
            'subscription_statistics',
            $statsCallback,
            900 // 15 minutes
        );
    }

    /**
     * Cache customer payment methods
     */
    public function cacheCustomerPaymentMethods(string $customerId, callable $methodsCallback): array
    {
        return $this->remember(
            "customer_payment_methods_{$customerId}",
            $methodsCallback,
            1800 // 30 minutes
        );
    }

    /**
     * Cache transaction history
     */
    public function cacheTransactionHistory(string $customerId, callable $historyCallback): array
    {
        return $this->remember(
            "transaction_history_{$customerId}",
            $historyCallback,
            600 // 10 minutes
        );
    }

    /**
     * Cache payment gateway configuration
     */
    public function cacheGatewayConfig(callable $configCallback): array
    {
        return $this->remember(
            'payment_gateway_config',
            $configCallback,
            7200 // 2 hours
        );
    }

    /**
     * Invalidate cache for a specific key
     */
    public function invalidate(string $key): bool
    {
        $cacheKey = $this->generateKey($key);
        $result = $this->cache->deleteItem($cacheKey);

        $this->logger->info('Cache invalidated', [
            'key' => $key,
            'success' => $result
        ]);

        return $result;
    }

    /**
     * Invalidate customer-related caches
     */
    public function invalidateCustomerCache(string $customerId): void
    {
        $keys = [
            "customer_payment_methods_{$customerId}",
            "transaction_history_{$customerId}",
            "customer_subscriptions_{$customerId}"
        ];

        foreach ($keys as $key) {
            $this->invalidate($key);
        }
    }

    /**
     * Invalidate subscription-related caches
     */
    public function invalidateSubscriptionCache(): void
    {
        $this->invalidate('subscription_statistics');
    }

    /**
     * Warm up critical caches
     */
    public function warmUpCaches(): array
    {
        $results = [];
        
        try {
            // Warm up subscription statistics
            $this->cacheSubscriptionStats(function() {
                // This would normally call the actual service
                return ['total' => 0, 'active' => 0, 'cancelled' => 0];
            });
            $results['subscription_stats'] = 'success';
        } catch (\Exception $e) {
            $results['subscription_stats'] = 'failed: ' . $e->getMessage();
        }

        try {
            // Warm up gateway configuration
            $this->cacheGatewayConfig(function() {
                return ['gateway' => 'nmi', 'environment' => 'sandbox'];
            });
            $results['gateway_config'] = 'success';
        } catch (\Exception $e) {
            $results['gateway_config'] = 'failed: ' . $e->getMessage();
        }

        $this->logger->info('Cache warm-up completed', $results);
        
        return $results;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $stats = [
            'cache_adapter' => get_class($this->cache),
            'timestamp' => time()
        ];

        // Try to get adapter-specific stats if available
        if (method_exists($this->cache, 'getStats')) {
            $stats['adapter_stats'] = $this->cache->getStats();
        }

        return $stats;
    }

    /**
     * Clear all application caches
     */
    public function clearAll(): bool
    {
        try {
            $result = $this->cache->clear();
            
            $this->logger->info('All caches cleared', ['success' => $result]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear caches', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Set cache data directly
     */
    public function set(string $key, mixed $data, int $ttl = self::DEFAULT_TTL): bool
    {
        try {
            $cacheKey = $this->generateKey($key);
            $cacheItem = $this->cache->getItem($cacheKey);
            
            $cacheItem->set($data);
            $cacheItem->expiresAfter($ttl);
            
            $result = $this->cache->save($cacheItem);
            
            $this->logger->debug('Cache set', [
                'key' => $key,
                'ttl' => $ttl,
                'success' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to set cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get cache data directly
     */
    public function get(string $key): mixed
    {
        try {
            $cacheKey = $this->generateKey($key);
            $cacheItem = $this->cache->getItem($cacheKey);
            
            if ($cacheItem->isHit()) {
                $this->logger->debug('Cache hit', ['key' => $key]);
                return $cacheItem->get();
            }
            
            $this->logger->debug('Cache miss', ['key' => $key]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Check if cache key exists
     */
    public function has(string $key): bool
    {
        try {
            $cacheKey = $this->generateKey($key);
            $cacheItem = $this->cache->getItem($cacheKey);
            
            return $cacheItem->isHit();
        } catch (\Exception $e) {
            $this->logger->error('Failed to check cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Generate cache key with prefix
     */
    private function generateKey(string $key): string
    {
        // Sanitize key to ensure it's valid for cache
        $sanitizedKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
        return self::CACHE_PREFIX . $sanitizedKey;
    }

    /**
     * Cache with tags for easier invalidation
     */
    public function rememberWithTags(string $key, array $tags, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        // For now, just use regular remember
        // In a more advanced implementation, you could use a cache adapter that supports tags
        return $this->remember($key, $callback, $ttl);
    }

    /**
     * Invalidate all caches with specific tags
     */
    public function invalidateByTags(array $tags): void
    {
        // For now, just log the tags
        // In a more advanced implementation, you could use a cache adapter that supports tags
        $this->logger->info('Cache invalidation by tags requested', ['tags' => $tags]);
    }
}