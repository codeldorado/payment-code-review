<?php

namespace App\Tests\Service;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Service\PaymentGatewayInterface;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SubscriptionServiceTest extends TestCase
{
    private $entityManager;
    private $subscriptionRepository;
    private $paymentGateway;
    private $logger;
    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->subscriptionService = new SubscriptionService(
            $this->entityManager,
            $this->subscriptionRepository,
            $this->paymentGateway,
            $this->logger
        );
    }

    public function testCreateSubscription(): void
    {
        $customerId = 'customer_123';
        $amount = 29.99;
        $currency = 'USD';
        $frequency = 'monthly';
        $metadata = ['plan' => 'premium'];

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Subscription::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Subscription created', $this->isType('array'));

        $subscription = $this->subscriptionService->createSubscription(
            $customerId,
            $amount,
            $currency,
            $frequency,
            $metadata
        );

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($customerId, $subscription->getCustomerId());
        $this->assertEquals($amount, $subscription->getAmount());
        $this->assertEquals($currency, $subscription->getCurrency());
        $this->assertEquals($frequency, $subscription->getFrequency());
        $this->assertEquals('active', $subscription->getStatus());
        $this->assertNotNull($subscription->getUuid());
        $this->assertNotNull($subscription->getCreatedAt());
        $this->assertNotNull($subscription->getNextBillingDate());
    }

    public function testCancelSubscriptionSuccess(): void
    {
        $subscriptionUuid = 'sub_123';
        $subscription = new Subscription();
        $subscription->setCustomerId('customer_123');
        $subscription->setAmount(29.99);
        $subscription->setCurrency('USD');
        $subscription->setFrequency('monthly');

        $this->subscriptionRepository->expects($this->once())
            ->method('findByUuid')
            ->with($subscriptionUuid)
            ->willReturn($subscription);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Subscription cancelled', $this->isType('array'));

        $result = $this->subscriptionService->cancelSubscription($subscriptionUuid);

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $subscription->getStatus());
        $this->assertNotNull($subscription->getCancelledAt());
    }

    public function testCancelSubscriptionNotFound(): void
    {
        $subscriptionUuid = 'sub_nonexistent';

        $this->subscriptionRepository->expects($this->once())
            ->method('findByUuid')
            ->with($subscriptionUuid)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Subscription not found for cancellation', ['uuid' => $subscriptionUuid]);

        $result = $this->subscriptionService->cancelSubscription($subscriptionUuid);

        $this->assertFalse($result);
    }

    public function testProcessSingleRebillingSuccess(): void
    {
        $subscription = new Subscription();
        $subscription->setCustomerId('customer_123');
        $subscription->setAmount(29.99);
        $subscription->setCurrency('USD');
        $subscription->setFrequency('monthly');
        $subscription->setBillingCycle(5);

        $gatewayResult = [
            'status' => 'success',
            'transaction_id' => 'txn_123'
        ];

        $this->paymentGateway->expects($this->once())
            ->method('processRebilling')
            ->with(
                'customer_123',
                29.99,
                'USD',
                $this->isType('array')
            )
            ->willReturn($gatewayResult);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Rebilling successful', $this->isType('array'));

        $result = $this->subscriptionService->processSingleRebilling($subscription);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('txn_123', $result['transaction_id']);
        $this->assertEquals(6, $subscription->getBillingCycle());
        $this->assertNotNull($subscription->getLastBillingDate());
        $this->assertNotNull($subscription->getNextBillingDate());
    }

    public function testProcessSingleRebillingFailure(): void
    {
        $subscription = new Subscription();
        $subscription->setCustomerId('customer_123');
        $subscription->setAmount(29.99);
        $subscription->setCurrency('USD');
        $subscription->setFrequency('monthly');

        $gatewayResult = [
            'status' => 'error',
            'message' => 'Payment declined'
        ];

        $this->paymentGateway->expects($this->once())
            ->method('processRebilling')
            ->willReturn($gatewayResult);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Rebilling failed', $this->isType('array'));

        $result = $this->subscriptionService->processSingleRebilling($subscription);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Payment declined', $result['message']);
    }

    public function testProcessSingleRebillingInactiveSubscription(): void
    {
        $subscription = new Subscription();
        $subscription->setCustomerId('customer_123');
        $subscription->setAmount(29.99);
        $subscription->setCurrency('USD');
        $subscription->setFrequency('monthly');
        $subscription->cancel(); // Make it inactive

        $result = $this->subscriptionService->processSingleRebilling($subscription);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Subscription is not active', $result['message']);
    }

    public function testProcessDueBilling(): void
    {
        $subscription1 = new Subscription();
        $subscription1->setCustomerId('customer_1');
        $subscription1->setAmount(19.99);
        $subscription1->setCurrency('USD');
        $subscription1->setFrequency('monthly');

        $subscription2 = new Subscription();
        $subscription2->setCustomerId('customer_2');
        $subscription2->setAmount(39.99);
        $subscription2->setCurrency('USD');
        $subscription2->setFrequency('monthly');

        $dueSubscriptions = [$subscription1, $subscription2];

        $this->subscriptionRepository->expects($this->once())
            ->method('findDueForBilling')
            ->willReturn($dueSubscriptions);

        $this->paymentGateway->expects($this->exactly(2))
            ->method('processRebilling')
            ->willReturn(['status' => 'success', 'transaction_id' => 'txn_123']);

        $results = $this->subscriptionService->processDueBilling();

        $this->assertCount(2, $results);
        $this->assertEquals('customer_1', $results[0]['customer_id']);
        $this->assertEquals('customer_2', $results[1]['customer_id']);
    }

    public function testGetStatistics(): void
    {
        $expectedStats = [
            'total' => 100,
            'active' => 85,
            'cancelled' => 15
        ];

        $this->subscriptionRepository->expects($this->once())
            ->method('getStatistics')
            ->willReturn($expectedStats);

        $stats = $this->subscriptionService->getStatistics();

        $this->assertEquals($expectedStats, $stats);
    }
}