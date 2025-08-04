<?php

namespace App\EventListener;

use App\Service\PerformanceMonitoringService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener for performance monitoring
 */
class PerformanceEventListener
{
    private array $requestTimings = [];

    public function __construct(
        private PerformanceMonitoringService $performanceMonitoring
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 1000)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = spl_object_hash($request);
        
        $this->requestTimings[$requestId] = $this->performanceMonitoring->startTiming('http_request');
        $this->requestTimings[$requestId]['path'] = $request->getPathInfo();
        $this->requestTimings[$requestId]['method'] = $request->getMethod();
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1000)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $requestId = spl_object_hash($request);

        if (!isset($this->requestTimings[$requestId])) {
            return;
        }

        $timing = $this->requestTimings[$requestId];
        
        $context = [
            'path' => $timing['path'],
            'method' => $timing['method'],
            'status_code' => $response->getStatusCode(),
            'content_length' => strlen($response->getContent()),
        ];

        $duration = $this->performanceMonitoring->endTiming($timing, $context);

        // Record specific metrics for API endpoints
        if (str_starts_with($timing['path'], '/api/') || 
            str_starts_with($timing['path'], '/subscription/api/')) {
            
            $this->performanceMonitoring->recordApiCall(
                $timing['path'],
                $timing['method'],
                $duration,
                $response->getStatusCode()
            );
        }

        // Clean up
        unset($this->requestTimings[$requestId]);
    }
}