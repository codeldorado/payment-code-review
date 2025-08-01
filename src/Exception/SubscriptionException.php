<?php

namespace App\Exception;

/**
 * Exception for subscription-related errors
 */
class SubscriptionException extends \Exception
{
    protected string $errorCode;
    protected array $context;

    public function __construct(
        string $message = '',
        string $errorCode = 'SUBSCRIPTION_ERROR',
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}