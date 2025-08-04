<?php

namespace App\Tests\Integration;

use App\Entity\Subscription;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
            
        $this->subscriptionService = $kernel->getContainer()
            ->get(SubscriptionService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\Subscription')->execute();
        $this->entityManager->close();
    }

    public function testCreateAndRetrieveSubscription(): void
    {
        $customerId = 'test_customer_' . uniqid();
        $amount = 29.99;
        $currency = 'USD';
        $frequency = 'monthly';
        $metadata = ['plan' => 'premium', 'test' => true];

        // Create subscription
        $subscription = $this->subscriptionService->createSubscription(
            $customerId,
            $amount,
            $currency,
            $frequency,
            $metadata
        );

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertNotNull($subscription->getId());
        $this->assertNotNull($subscription->getUuid());
        $this->assertEquals($customerId, $subscription->getCustomerId());
        $this->assertEquals($amount, $subscription->getAmount());
        $this->assertEquals($currency, $subscription->getCurrency());
        $this->assertEquals($frequency, $subscription->getFrequency());
        $this->assertEquals('active', $subscription->getStatus());
        $this->assertTrue($subscription->isActive());

        // Retrieve subscription
        $retrievedSubscription = $this->subscriptionService->getSubscription($subscription->getUuid());
        
        $this->assertNotNull($retrievedSubscription);
        $this->assertEquals($subscription->getId(), $retrievedSubscription->getId());
        $this->assertEquals($customerId, $retrievedSubscription->getCustomerId());
    }

    public function testSubscriptionLifecycle(): void
    {
        $customerId = 'test_customer_' . uniqid();
        
        // Create subscription
        $subscription = $this->subscriptionService->createSubscription(
            $customerId,
            19.99,
            'USD',
            'monthly'
        );

        $this->assertTrue($subscription->isActive());
        $this->assertNull($subscription->getCancelledAt());

        // Cancel subscription
        $result = $this->subscriptionService->cancelSubscription($subscription->getUuid());
        
        $this->assertTrue($result);
        
        // Refresh from database
        $this->entityManager->refresh($subscription);
        
        $this->assertFalse($subscription->isActive());
        $this->assertEquals('cancelled', $subscription->getStatus());
        $this->assertNotNull($subscription->getCancelledAt());
    }

    public function testCustomerSubscriptions(): void
    {
        $customerId = 'test_customer_' . uniqid();
        
        // Create multiple subscriptions for the same customer
        $subscription1 = $this->subscriptionService->createSubscription(
            $customerId,
            9.99,
            'USD',
            'monthly'
        );
        
        $subscription2 = $this->subscriptionService->createSubscription(
            $customerId,
            19.99,
            'USD',
            'yearly'
        );
        
        // Create subscription for different customer
        $this->subscriptionService->createSubscription(
            'other_customer',
            29.99,
            'USD',
            'monthly'
        );

        // Get subscriptions for our test customer
        $customerSubscriptions = $this->subscriptionService->getCustomerSubscriptions($customerId);
        
        $this->assertCount(2, $customerSubscriptions);
        
        $subscriptionIds = array_map(fn($sub) => $sub->getId(), $customerSubscriptions);
        $this->assertContains($subscription1->getId(), $subscriptionIds);
        $this->assertContains($subscription2->getId(), $subscriptionIds);
    }

    public function testSubscriptionFrequencyCalculation(): void
    {
        $customerId = 'test_customer_' . uniqid();
        
        // Test different frequencies
        $frequencies = ['daily', 'weekly', 'monthly', 'yearly'];
        
        foreach ($frequencies as $frequency) {
            $subscription = $this->subscriptionService->createSubscription(
                $customerId . '_' . $frequency,
                10.00,
                'USD',
                $frequency
            );
            
            $createdAt = $subscription->getCreatedAt();
            $nextBillingDate = $subscription->getNextBillingDate();
            
            $this->assertNotNull($nextBillingDate);
            $this->assertGreaterThan($createdAt, $nextBillingDate);
            
            // Verify the interval is correct
            $interval = $createdAt->diff($nextBillingDate);
            
            switch ($frequency) {
                case 'daily':
                    $this->assertEquals(1, $interval->days);
                    break;
                case 'weekly':
                    $this->assertEquals(7, $interval->days);
                    break;
                case 'monthly':
                    $this->assertEquals(1, $interval->m);
                    break;
                case 'yearly':
                    $this->assertEquals(1, $interval->y);
                    break;
            }
        }
    }

    public function testSubscriptionStatistics(): void
    {
        $customerId = 'test_customer_' . uniqid();
        
        // Create some test subscriptions
        $subscription1 = $this->subscriptionService->createSubscription(
            $customerId . '_1',
            10.00,
            'USD',
            'monthly'
        );
        
        $subscription2 = $this->subscriptionService->createSubscription(
            $customerId . '_2',
            20.00,
            'USD',
            'yearly'
        );
        
        // Cancel one subscription
        $this->subscriptionService->cancelSubscription($subscription2->getUuid());
        
        // Get statistics
        $stats = $this->subscriptionService->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('cancelled', $stats);
        
        $this->assertGreaterThanOrEqual(2, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['active']);
        $this->assertGreaterThanOrEqual(1, $stats['cancelled']);
    }
}