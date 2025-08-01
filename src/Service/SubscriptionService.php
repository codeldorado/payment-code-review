<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\PaymentTransaction;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing subscriptions and rebilling
 */
class SubscriptionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubscriptionRepository $subscriptionRepository,
        private PaymentGatewayInterface $paymentGateway,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a new subscription
     */
    public function createSubscription(
        string $customerId,
        float $amount,
        string $currency,
        string $frequency,
        array $metadata = []
    ): Subscription {
        $subscription = new Subscription();
        $subscription->setCustomerId($customerId);
        $subscription->setAmount($amount);
        $subscription->setCurrency($currency);
        $subscription->setFrequency($frequency);
        $subscription->setNextBillingDate($subscription->calculateNextBillingDate());
        
        if (!empty($metadata)) {
            $subscription->setMetadata(json_encode($metadata));
        }

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $this->logger->info('Subscription created', [
            'subscription_id' => $subscription->getUuid(),
            'customer_id' => $customerId,
            'amount' => $amount,
            'frequency' => $frequency
        ]);

        return $subscription;
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionUuid): bool
    {
        $subscription = $this->subscriptionRepository->findByUuid($subscriptionUuid);
        
        if (!$subscription) {
            $this->logger->warning('Subscription not found for cancellation', ['uuid' => $subscriptionUuid]);
            return false;
        }

        if (!$subscription->isActive()) {
            $this->logger->warning('Subscription already inactive', ['uuid' => $subscriptionUuid]);
            return false;
        }

        $subscription->cancel();
        $this->entityManager->flush();

        $this->logger->info('Subscription cancelled', [
            'subscription_id' => $subscriptionUuid,
            'customer_id' => $subscription->getCustomerId()
        ]);

        return true;
    }

    /**
     * Process rebilling for due subscriptions
     */
    public function processDueBilling(\DateTime $date = null): array
    {
        $date = $date ?? new \DateTime();
        $dueSubscriptions = $this->subscriptionRepository->findDueForBilling($date);
        $results = [];

        foreach ($dueSubscriptions as $subscription) {
            $result = $this->processSingleRebilling($subscription);
            $results[] = [
                'subscription_id' => $subscription->getUuid(),
                'customer_id' => $subscription->getCustomerId(),
                'result' => $result
            ];
        }

        return $results;
    }

    /**
     * Process rebilling for a single subscription
     */
    public function processSingleRebilling(Subscription $subscription): array
    {
        if (!$subscription->isActive()) {
            return ['status' => 'error', 'message' => 'Subscription is not active'];
        }

        try {
            // Process the rebilling through the payment gateway
            $result = $this->paymentGateway->processRebilling(
                $subscription->getCustomerId(),
                $subscription->getAmount(),
                $subscription->getCurrency(),
                [
                    'subscription_id' => $subscription->getUuid(),
                    'billing_cycle' => $subscription->getBillingCycle() + 1
                ]
            );

            if ($result['status'] === 'success') {
                // Update subscription billing information
                $subscription->setLastBillingDate(new \DateTime());
                $subscription->setNextBillingDate($subscription->calculateNextBillingDate());
                $subscription->setBillingCycle($subscription->getBillingCycle() + 1);

                $this->entityManager->flush();

                $this->logger->info('Rebilling successful', [
                    'subscription_id' => $subscription->getUuid(),
                    'customer_id' => $subscription->getCustomerId(),
                    'amount' => $subscription->getAmount(),
                    'transaction_id' => $result['transaction_id'] ?? null
                ]);

                return $result;
            } else {
                $this->logger->error('Rebilling failed', [
                    'subscription_id' => $subscription->getUuid(),
                    'customer_id' => $subscription->getCustomerId(),
                    'error' => $result['message'] ?? 'Unknown error'
                ]);

                return $result;
            }
        } catch (\Exception $e) {
            $this->logger->error('Rebilling exception', [
                'subscription_id' => $subscription->getUuid(),
                'customer_id' => $subscription->getCustomerId(),
                'exception' => $e->getMessage()
            ]);

            return ['status' => 'error', 'message' => 'Processing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get subscription by UUID
     */
    public function getSubscription(string $uuid): ?Subscription
    {
        return $this->subscriptionRepository->findByUuid($uuid);
    }

    /**
     * Get active subscriptions for a customer
     */
    public function getCustomerSubscriptions(string $customerId): array
    {
        return $this->subscriptionRepository->findActiveByCustomer($customerId);
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): array
    {
        return $this->subscriptionRepository->getStatistics();
    }
}