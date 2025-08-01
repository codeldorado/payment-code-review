<?php

namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Service for input validation and sanitization
 */
class ValidationService
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    /**
     * Validate payment amount
     */
    public function validateAmount(float $amount): array
    {
        $violations = $this->validator->validate($amount, [
            new Assert\NotBlank(),
            new Assert\Positive(),
            new Assert\Range(['min' => 0.01, 'max' => 999999.99])
        ]);

        return $this->formatViolations($violations);
    }

    /**
     * Validate currency code
     */
    public function validateCurrency(string $currency): array
    {
        $violations = $this->validator->validate($currency, [
            new Assert\NotBlank(),
            new Assert\Length(['min' => 3, 'max' => 3]),
            new Assert\Regex(['pattern' => '/^[A-Z]{3}$/', 'message' => 'Currency must be 3 uppercase letters'])
        ]);

        return $this->formatViolations($violations);
    }

    /**
     * Validate customer ID
     */
    public function validateCustomerId(string $customerId): array
    {
        $violations = $this->validator->validate($customerId, [
            new Assert\NotBlank(),
            new Assert\Length(['min' => 3, 'max' => 255]),
            new Assert\Regex(['pattern' => '/^[a-zA-Z0-9_-]+$/', 'message' => 'Customer ID can only contain letters, numbers, underscores, and hyphens'])
        ]);

        return $this->formatViolations($violations);
    }

    /**
     * Validate transaction ID
     */
    public function validateTransactionId(string $transactionId): array
    {
        $violations = $this->validator->validate($transactionId, [
            new Assert\NotBlank(),
            new Assert\Length(['min' => 5, 'max' => 100]),
            new Assert\Regex(['pattern' => '/^[a-zA-Z0-9_-]+$/', 'message' => 'Transaction ID contains invalid characters'])
        ]);

        return $this->formatViolations($violations);
    }

    /**
     * Validate subscription frequency
     */
    public function validateFrequency(string $frequency): array
    {
        $violations = $this->validator->validate($frequency, [
            new Assert\NotBlank(),
            new Assert\Choice(['choices' => ['daily', 'weekly', 'monthly', 'yearly']])
        ]);

        return $this->formatViolations($violations);
    }

    /**
     * Validate UUID format
     */
    public function validateUuid(string $uuid): array
    {
        $violations = $this->validator->validate($uuid, [
            new Assert\NotBlank(),
            new Assert\Uuid()
        ]);

        return $this->formatViolations($violations);
    }

    /**
     * Sanitize string input
     */
    public function sanitizeString(string $input): string
    {
        // Remove null bytes and control characters
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

        // Trim whitespace
        $sanitized = trim($sanitized);

        // Convert to UTF-8 if needed
        if (!mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = mb_convert_encoding($sanitized, 'UTF-8', 'auto');
        }

        return $sanitized;
    }

    /**
     * Sanitize numeric input
     */
    public function sanitizeNumeric(mixed $input): float
    {
        if (is_numeric($input)) {
            return (float) $input;
        }

        // Remove non-numeric characters except decimal point
        $sanitized = preg_replace('/[^0-9.]/', '', (string) $input);

        return (float) $sanitized;
    }

    /**
     * Format validation violations into array
     */
    private function formatViolations($violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = $violation->getMessage();
        }
        return $errors;
    }

    /**
     * Validate all payment data at once
     */
    public function validatePaymentData(array $data): array
    {
        $errors = [];

        if (isset($data['amount'])) {
            $amountErrors = $this->validateAmount($this->sanitizeNumeric($data['amount']));
            if (!empty($amountErrors)) {
                $errors['amount'] = $amountErrors;
            }
        }

        if (isset($data['currency'])) {
            $currencyErrors = $this->validateCurrency($this->sanitizeString($data['currency']));
            if (!empty($currencyErrors)) {
                $errors['currency'] = $currencyErrors;
            }
        }

        if (isset($data['customer_id'])) {
            $customerErrors = $this->validateCustomerId($this->sanitizeString($data['customer_id']));
            if (!empty($customerErrors)) {
                $errors['customer_id'] = $customerErrors;
            }
        }

        return $errors;
    }
}
