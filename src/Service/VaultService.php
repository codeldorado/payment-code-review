<?php

namespace App\Service;

use App\Entity\PaymentVault;
use App\Repository\PaymentVaultRepository;
use App\Exception\PaymentException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing payment vault operations
 */
class VaultService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentVaultRepository $vaultRepository,
        private PaymentGatewayInterface $paymentGateway,
        private ValidationService $validationService,
        private LoggerInterface $logger
    ) {}

    /**
     * Store a payment method in the vault
     */
    public function storePaymentMethod(
        string $customerId,
        string $gatewayCustomerId,
        string $paymentMethodToken,
        array $paymentMethodData
    ): PaymentVault {
        // Validate input
        $validationErrors = $this->validationService->validateCustomerId($customerId);
        if (!empty($validationErrors)) {
            throw new PaymentException(
                'Invalid customer ID: ' . implode(', ', $validationErrors),
                'VALIDATION_ERROR'
            );
        }

        $vault = new PaymentVault();
        $vault->setCustomerId($customerId);
        $vault->setGatewayCustomerId($gatewayCustomerId);
        $vault->setPaymentMethodToken($paymentMethodToken);
        
        // Set payment method details
        if (isset($paymentMethodData['type'])) {
            $vault->setPaymentMethodType($paymentMethodData['type']);
        }
        
        if (isset($paymentMethodData['last4'])) {
            $vault->setLast4Digits($paymentMethodData['last4']);
        }
        
        if (isset($paymentMethodData['brand'])) {
            $vault->setCardBrand($paymentMethodData['brand']);
        }
        
        if (isset($paymentMethodData['exp_month'])) {
            $vault->setExpiryMonth(str_pad($paymentMethodData['exp_month'], 2, '0', STR_PAD_LEFT));
        }
        
        if (isset($paymentMethodData['exp_year'])) {
            $vault->setExpiryYear($paymentMethodData['exp_year']);
        }
        
        if (isset($paymentMethodData['billing_name'])) {
            $vault->setBillingName($paymentMethodData['billing_name']);
        }
        
        if (isset($paymentMethodData['billing_address'])) {
            $vault->setBillingAddress($paymentMethodData['billing_address']);
        }

        // Check if this should be the default payment method
        $existingMethods = $this->vaultRepository->findActiveByCustomer($customerId);
        if (empty($existingMethods)) {
            $vault->setIsDefault(true);
        }

        $this->entityManager->persist($vault);
        $this->entityManager->flush();

        $this->logger->info('Payment method stored in vault', [
            'vault_id' => $vault->getUuid(),
            'customer_id' => $customerId,
            'payment_method_type' => $vault->getPaymentMethodType(),
            'is_default' => $vault->isDefault()
        ]);

        return $vault;
    }

    /**
     * Get customer's payment methods
     */
    public function getCustomerPaymentMethods(string $customerId): array
    {
        return $this->vaultRepository->findActiveByCustomer($customerId);
    }

    /**
     * Get default payment method for customer
     */
    public function getDefaultPaymentMethod(string $customerId): ?PaymentVault
    {
        return $this->vaultRepository->findDefaultByCustomer($customerId);
    }

    /**
     * Set payment method as default
     */
    public function setAsDefault(string $vaultUuid): bool
    {
        $vault = $this->vaultRepository->findByUuid($vaultUuid);
        
        if (!$vault || !$vault->isActive()) {
            return false;
        }

        $this->vaultRepository->setAsDefault($vault);

        $this->logger->info('Payment method set as default', [
            'vault_id' => $vaultUuid,
            'customer_id' => $vault->getCustomerId()
        ]);

        return true;
    }

    /**
     * Deactivate a payment method
     */
    public function deactivatePaymentMethod(string $vaultUuid): bool
    {
        $vault = $this->vaultRepository->findByUuid($vaultUuid);
        
        if (!$vault || !$vault->isActive()) {
            return false;
        }

        $this->vaultRepository->deactivate($vault);

        $this->logger->info('Payment method deactivated', [
            'vault_id' => $vaultUuid,
            'customer_id' => $vault->getCustomerId()
        ]);

        return true;
    }

    /**
     * Process payment using stored payment method
     */
    public function processPaymentWithVault(
        string $vaultUuid,
        float $amount,
        string $currency = 'USD',
        array $metadata = []
    ): array {
        $vault = $this->vaultRepository->findByUuid($vaultUuid);
        
        if (!$vault || !$vault->isActive()) {
            throw new PaymentException(
                'Payment method not found or inactive',
                'VAULT_NOT_FOUND'
            );
        }

        if ($vault->isExpired()) {
            throw new PaymentException(
                'Payment method has expired',
                'PAYMENT_METHOD_EXPIRED'
            );
        }

        try {
            // Process payment using the stored payment method
            $result = $this->paymentGateway->processRebilling(
                $vault->getGatewayCustomerId(),
                $amount,
                $currency,
                array_merge($metadata, [
                    'vault_id' => $vaultUuid,
                    'payment_method_token' => $vault->getPaymentMethodToken()
                ])
            );

            if ($result['status'] === 'success') {
                // Update last used timestamp
                $vault->markAsUsed();
                $this->entityManager->flush();

                $this->logger->info('Vault payment processed successfully', [
                    'vault_id' => $vaultUuid,
                    'customer_id' => $vault->getCustomerId(),
                    'amount' => $amount,
                    'transaction_id' => $result['transaction_id'] ?? null
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Vault payment processing failed', [
                'vault_id' => $vaultUuid,
                'customer_id' => $vault->getCustomerId(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw new PaymentException(
                'Payment processing failed: ' . $e->getMessage(),
                'PAYMENT_PROCESSING_ERROR',
                ['vault_id' => $vaultUuid],
                0,
                $e
            );
        }
    }

    /**
     * Get vault statistics
     */
    public function getStatistics(): array
    {
        return $this->vaultRepository->getStatistics();
    }

    /**
     * Clean up expired payment methods
     */
    public function cleanupExpiredMethods(): int
    {
        $expiredMethods = $this->vaultRepository->findExpired();
        $count = 0;

        foreach ($expiredMethods as $method) {
            $this->vaultRepository->deactivate($method);
            $count++;

            $this->logger->info('Expired payment method deactivated', [
                'vault_id' => $method->getUuid(),
                'customer_id' => $method->getCustomerId(),
                'expiry' => $method->getExpiryMonth() . '/' . $method->getExpiryYear()
            ]);
        }

        return $count;
    }

    /**
     * Get payment method by UUID
     */
    public function getPaymentMethod(string $uuid): ?PaymentVault
    {
        return $this->vaultRepository->findByUuid($uuid);
    }
}