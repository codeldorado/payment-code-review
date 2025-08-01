<?php

namespace App\EventListener;

use App\Service\RateLimitService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use App\Exception\PaymentException;
use App\Exception\PaymentGatewayException;
use App\Exception\SubscriptionException;

/**
 * Event listener for security and error handling
 */
class SecurityEventListener
{
    public function __construct(
        private RateLimitService $rateLimitService,
        private LoggerInterface $logger
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only apply rate limiting to API endpoints
        if (!str_starts_with($request->getPathInfo(), '/api/') && 
            !str_starts_with($request->getPathInfo(), '/subscription/api/')) {
            return;
        }

        // Check rate limit
        if (!$this->rateLimitService->isAllowed($request)) {
            $usage = $this->rateLimitService->getCurrentUsage($request);
            
            $this->logger->warning('Rate limit exceeded', [
                'ip' => $request->getClientIp(),
                'path' => $request->getPathInfo(),
                'user_agent' => $request->headers->get('User-Agent'),
                'usage' => $usage
            ]);

            $response = new JsonResponse([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $usage['reset_time'] - time()
            ], 429);

            $response->headers->set('X-RateLimit-Limit', (string) $usage['limit']);
            $response->headers->set('X-RateLimit-Remaining', (string) $usage['remaining']);
            $response->headers->set('X-RateLimit-Reset', (string) $usage['reset_time']);
            $response->headers->set('Retry-After', (string) ($usage['reset_time'] - time()));

            $event->setResponse($response);
        }
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Handle payment-related exceptions
        if ($exception instanceof PaymentException || 
            $exception instanceof PaymentGatewayException || 
            $exception instanceof SubscriptionException) {
            
            $this->logger->error('Payment system error', [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'error_code' => method_exists($exception, 'getErrorCode') ? $exception->getErrorCode() : 'UNKNOWN',
                'context' => method_exists($exception, 'getContext') ? $exception->getContext() : [],
                'ip' => $request->getClientIp(),
                'path' => $request->getPathInfo(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            // For API requests, return JSON error response
            if (str_starts_with($request->getPathInfo(), '/api/') || 
                str_starts_with($request->getPathInfo(), '/subscription/api/')) {
                
                $response = new JsonResponse([
                    'error' => method_exists($exception, 'getErrorCode') ? $exception->getErrorCode() : 'PAYMENT_ERROR',
                    'message' => $exception->getMessage(),
                    'timestamp' => date('c')
                ], 400);

                $event->setResponse($response);
            }
        }

        // Log all other exceptions for security monitoring
        if (!($exception instanceof PaymentException) && 
            !($exception instanceof PaymentGatewayException) && 
            !($exception instanceof SubscriptionException)) {
            
            $this->logger->error('Unhandled exception', [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'ip' => $request->getClientIp(),
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
        }
    }
}