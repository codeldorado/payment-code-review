<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service for rate limiting API requests
 */
class RateLimitService
{
    private const DEFAULT_LIMIT = 100; // requests per hour
    private const DEFAULT_WINDOW = 3600; // 1 hour in seconds

    public function __construct(
        private CacheItemPoolInterface $cache
    ) {}

    /**
     * Check if request is within rate limit
     */
    public function isAllowed(Request $request, int $limit = self::DEFAULT_LIMIT, int $window = self::DEFAULT_WINDOW): bool
    {
        $key = $this->generateKey($request);
        $cacheItem = $this->cache->getItem($key);

        if (!$cacheItem->isHit()) {
            // First request in window
            $cacheItem->set(1);
            $cacheItem->expiresAfter($window);
            $this->cache->save($cacheItem);
            return true;
        }

        $currentCount = $cacheItem->get();
        
        if ($currentCount >= $limit) {
            return false;
        }

        // Increment counter
        $cacheItem->set($currentCount + 1);
        $this->cache->save($cacheItem);
        
        return true;
    }

    /**
     * Get current usage for a request
     */
    public function getCurrentUsage(Request $request): array
    {
        $key = $this->generateKey($request);
        $cacheItem = $this->cache->getItem($key);

        if (!$cacheItem->isHit()) {
            return [
                'current' => 0,
                'limit' => self::DEFAULT_LIMIT,
                'remaining' => self::DEFAULT_LIMIT,
                'reset_time' => time() + self::DEFAULT_WINDOW
            ];
        }

        $current = $cacheItem->get();
        $metadata = $cacheItem->getMetadata();
        $resetTime = $metadata['expiry'] ?? (time() + self::DEFAULT_WINDOW);

        return [
            'current' => $current,
            'limit' => self::DEFAULT_LIMIT,
            'remaining' => max(0, self::DEFAULT_LIMIT - $current),
            'reset_time' => $resetTime
        ];
    }

    /**
     * Generate cache key for request
     */
    private function generateKey(Request $request): string
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $userAgent = $request->headers->get('User-Agent', 'unknown');
        
        // Create a hash to avoid key length issues
        $identifier = hash('sha256', $ip . '|' . $userAgent);
        
        return 'rate_limit_' . $identifier;
    }

    /**
     * Reset rate limit for a request (admin function)
     */
    public function reset(Request $request): bool
    {
        $key = $this->generateKey($request);
        return $this->cache->deleteItem($key);
    }
}