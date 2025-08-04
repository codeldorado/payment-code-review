<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Service for monitoring application performance metrics
 */
class PerformanceMonitoringService
{
    private const METRICS_PREFIX = 'perf_metrics_';
    private const METRICS_TTL = 3600; // 1 hour

    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Record a performance metric
     */
    public function recordMetric(string $operation, float $duration, array $context = []): void
    {
        $timestamp = time();
        $metric = [
            'operation' => $operation,
            'duration' => $duration,
            'timestamp' => $timestamp,
            'context' => $context
        ];

        // Log the metric
        $this->logger->info('Performance metric recorded', $metric);

        // Store in cache for aggregation
        $this->storeMetricInCache($operation, $duration, $timestamp);
    }

    /**
     * Start timing an operation
     */
    public function startTiming(string $operation): array
    {
        return [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
    }

    /**
     * End timing an operation and record the metric
     */
    public function endTiming(array $timing, array $context = []): float
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = $endTime - $timing['start_time'];
        $memoryUsed = $endMemory - $timing['start_memory'];
        
        $context['memory_used'] = $memoryUsed;
        $context['peak_memory'] = memory_get_peak_usage(true);
        
        $this->recordMetric($timing['operation'], $duration, $context);
        
        return $duration;
    }

    /**
     * Get performance statistics for an operation
     */
    public function getOperationStats(string $operation, int $timeWindow = 3600): array
    {
        $cacheKey = self::METRICS_PREFIX . $operation;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if (!$cacheItem->isHit()) {
            return [
                'operation' => $operation,
                'count' => 0,
                'avg_duration' => 0,
                'min_duration' => 0,
                'max_duration' => 0,
                'total_duration' => 0
            ];
        }
        
        $metrics = $cacheItem->get();
        $currentTime = time();
        
        // Filter metrics within time window
        $recentMetrics = array_filter($metrics, function($metric) use ($currentTime, $timeWindow) {
            return ($currentTime - $metric['timestamp']) <= $timeWindow;
        });
        
        if (empty($recentMetrics)) {
            return [
                'operation' => $operation,
                'count' => 0,
                'avg_duration' => 0,
                'min_duration' => 0,
                'max_duration' => 0,
                'total_duration' => 0
            ];
        }
        
        $durations = array_column($recentMetrics, 'duration');
        
        return [
            'operation' => $operation,
            'count' => count($recentMetrics),
            'avg_duration' => array_sum($durations) / count($durations),
            'min_duration' => min($durations),
            'max_duration' => max($durations),
            'total_duration' => array_sum($durations),
            'time_window' => $timeWindow
        ];
    }

    /**
     * Get overall system performance metrics
     */
    public function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'cpu_usage' => $this->getCpuUsage(),
            'timestamp' => time()
        ];
    }

    /**
     * Record database query performance
     */
    public function recordDatabaseQuery(string $query, float $duration, array $params = []): void
    {
        $context = [
            'query_type' => $this->getQueryType($query),
            'params_count' => count($params),
            'query_hash' => md5($query)
        ];
        
        $this->recordMetric('database_query', $duration, $context);
        
        // Log slow queries
        if ($duration > 1.0) { // Queries taking more than 1 second
            $this->logger->warning('Slow database query detected', [
                'duration' => $duration,
                'query' => $query,
                'params' => $params
            ]);
        }
    }

    /**
     * Record API call performance
     */
    public function recordApiCall(string $endpoint, string $method, float $duration, int $statusCode): void
    {
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'success' => $statusCode >= 200 && $statusCode < 300
        ];
        
        $this->recordMetric('api_call', $duration, $context);
        
        // Log slow API calls
        if ($duration > 5.0) { // API calls taking more than 5 seconds
            $this->logger->warning('Slow API call detected', [
                'duration' => $duration,
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode
            ]);
        }
    }

    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        
        // Check for slow operations
        $operations = ['payment_processing', 'subscription_creation', 'database_query', 'api_call'];
        
        foreach ($operations as $operation) {
            $stats = $this->getOperationStats($operation);
            
            if ($stats['count'] > 0) {
                // Alert if average duration is too high
                $thresholds = [
                    'payment_processing' => 10.0,
                    'subscription_creation' => 5.0,
                    'database_query' => 1.0,
                    'api_call' => 5.0
                ];
                
                if (isset($thresholds[$operation]) && $stats['avg_duration'] > $thresholds[$operation]) {
                    $alerts[] = [
                        'type' => 'slow_operation',
                        'operation' => $operation,
                        'avg_duration' => $stats['avg_duration'],
                        'threshold' => $thresholds[$operation],
                        'severity' => 'warning'
                    ];
                }
            }
        }
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryLimit > 0 && ($memoryUsage / $memoryLimit) > 0.8) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'usage' => $memoryUsage,
                'limit' => $memoryLimit,
                'percentage' => ($memoryUsage / $memoryLimit) * 100,
                'severity' => 'critical'
            ];
        }
        
        return $alerts;
    }

    /**
     * Store metric in cache for aggregation
     */
    private function storeMetricInCache(string $operation, float $duration, int $timestamp): void
    {
        $cacheKey = self::METRICS_PREFIX . $operation;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        $metrics = $cacheItem->isHit() ? $cacheItem->get() : [];
        
        $metrics[] = [
            'duration' => $duration,
            'timestamp' => $timestamp
        ];
        
        // Keep only last 1000 metrics per operation
        if (count($metrics) > 1000) {
            $metrics = array_slice($metrics, -1000);
        }
        
        $cacheItem->set($metrics);
        $cacheItem->expiresAfter(self::METRICS_TTL);
        $this->cache->save($cacheItem);
    }

    /**
     * Get query type from SQL query
     */
    private function getQueryType(string $query): string
    {
        $query = trim(strtoupper($query));
        
        if (str_starts_with($query, 'SELECT')) return 'SELECT';
        if (str_starts_with($query, 'INSERT')) return 'INSERT';
        if (str_starts_with($query, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($query, 'DELETE')) return 'DELETE';
        
        return 'OTHER';
    }

    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage(): ?float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? null;
        }
        
        return null;
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}