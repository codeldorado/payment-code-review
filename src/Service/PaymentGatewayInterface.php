<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for payment gateway implementations
 * Provides a contract for different payment providers
 */
interface PaymentGatewayInterface
{
    /**
     * Initialize a payment transaction
     *
     * @param float $amount Payment amount
     * @param string $currency Currency code (e.g., 'USD')
     * @param string|null $redirectUrl URL to redirect after payment
     * @param array $billingInfo Billing information
     * @param array $shippingInfo Shipping information
     * @return array Payment initialization result
     */
    public function initializePayment(
        float $amount,
        string $currency = 'USD',
        ?string $redirectUrl = null,
        array $billingInfo = [],
        array $shippingInfo = []
    ): array;

    /**
     * Complete a payment transaction
     *
     * @param Request $request Request containing payment completion data
     * @return array Payment completion result
     */
    public function completeTransaction(Request $request): array;

    /**
     * Process a refund for a transaction
     *
     * @param string $originalTransactionId Original transaction ID to refund
     * @param float $refundAmount Amount to refund
     * @return array Refund processing result
     */
    public function processRefund(string $originalTransactionId, float $refundAmount): array;

    /**
     * Process a rebilling/subscription charge
     *
     * @param string $customerId Customer identifier
     * @param float $amount Amount to charge
     * @param string $currency Currency code
     * @param array $metadata Additional metadata
     * @return array Rebilling result
     */
    public function processRebilling(string $customerId, float $amount, string $currency = 'USD', array $metadata = []): array;
}