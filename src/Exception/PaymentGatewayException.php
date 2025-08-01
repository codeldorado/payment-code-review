<?php

namespace App\Exception;

/**
 * Exception for payment gateway communication errors
 */
class PaymentGatewayException extends PaymentException
{
    public function __construct(
        string $message = 'Payment gateway error',
        string $errorCode = 'GATEWAY_ERROR',
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $code, $previous);
    }
}