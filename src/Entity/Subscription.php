<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private ?string $uuid = null;

    #[ORM\Column(length: 255)]
    private ?string $customerId = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 50)]
    private ?string $frequency = null; // daily, weekly, monthly, yearly

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $nextBillingDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastBillingDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $cancelledAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $billingCycle = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metadata = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
        $this->status = 'active';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): static
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getNextBillingDate(): ?\DateTime
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(\DateTime $nextBillingDate): static
    {
        $this->nextBillingDate = $nextBillingDate;
        return $this;
    }

    public function getLastBillingDate(): ?\DateTime
    {
        return $this->lastBillingDate;
    }

    public function setLastBillingDate(?\DateTime $lastBillingDate): static
    {
        $this->lastBillingDate = $lastBillingDate;
        return $this;
    }

    public function getCancelledAt(): ?\DateTime
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTime $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getBillingCycle(): int
    {
        return $this->billingCycle;
    }

    public function setBillingCycle(int $billingCycle): static
    {
        $this->billingCycle = $billingCycle;
        return $this;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->cancelledAt === null;
    }

    /**
     * Cancel the subscription
     */
    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->cancelledAt = new \DateTime();
    }

    /**
     * Calculate next billing date based on frequency
     */
    public function calculateNextBillingDate(): \DateTime
    {
        $baseDate = $this->lastBillingDate ?? $this->createdAt ?? new \DateTime();
        
        return match ($this->frequency) {
            'daily' => (clone $baseDate)->modify('+1 day'),
            'weekly' => (clone $baseDate)->modify('+1 week'),
            'monthly' => (clone $baseDate)->modify('+1 month'),
            'yearly' => (clone $baseDate)->modify('+1 year'),
            default => throw new \InvalidArgumentException('Invalid frequency: ' . $this->frequency)
        };
    }
}