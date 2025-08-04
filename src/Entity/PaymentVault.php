<?php

namespace App\Entity;

use App\Repository\PaymentVaultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentVaultRepository::class)]
#[ORM\Table(name: 'payment_vault')]
class PaymentVault
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private ?string $uuid = null;

    #[ORM\Column(length: 255)]
    private ?string $customerId = null;

    #[ORM\Column(length: 255)]
    private ?string $gatewayCustomerId = null; // ID in the payment gateway's vault

    #[ORM\Column(length: 255)]
    private ?string $paymentMethodToken = null; // Token for the payment method

    #[ORM\Column(length: 20)]
    private ?string $paymentMethodType = null; // credit_card, bank_account, etc.

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $last4Digits = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cardBrand = null; // visa, mastercard, etc.

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $expiryMonth = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $expiryYear = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $billingAddress = null; // JSON encoded address

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastUsedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metadata = null; // JSON encoded additional data

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getGatewayCustomerId(): ?string
    {
        return $this->gatewayCustomerId;
    }

    public function setGatewayCustomerId(string $gatewayCustomerId): static
    {
        $this->gatewayCustomerId = $gatewayCustomerId;
        return $this;
    }

    public function getPaymentMethodToken(): ?string
    {
        return $this->paymentMethodToken;
    }

    public function setPaymentMethodToken(string $paymentMethodToken): static
    {
        $this->paymentMethodToken = $paymentMethodToken;
        return $this;
    }

    public function getPaymentMethodType(): ?string
    {
        return $this->paymentMethodType;
    }

    public function setPaymentMethodType(string $paymentMethodType): static
    {
        $this->paymentMethodType = $paymentMethodType;
        return $this;
    }

    public function getLast4Digits(): ?string
    {
        return $this->last4Digits;
    }

    public function setLast4Digits(?string $last4Digits): static
    {
        $this->last4Digits = $last4Digits;
        return $this;
    }

    public function getCardBrand(): ?string
    {
        return $this->cardBrand;
    }

    public function setCardBrand(?string $cardBrand): static
    {
        $this->cardBrand = $cardBrand;
        return $this;
    }

    public function getExpiryMonth(): ?string
    {
        return $this->expiryMonth;
    }

    public function setExpiryMonth(?string $expiryMonth): static
    {
        $this->expiryMonth = $expiryMonth;
        return $this;
    }

    public function getExpiryYear(): ?string
    {
        return $this->expiryYear;
    }

    public function setExpiryYear(?string $expiryYear): static
    {
        $this->expiryYear = $expiryYear;
        return $this;
    }

    public function getBillingName(): ?string
    {
        return $this->billingName;
    }

    public function setBillingName(?string $billingName): static
    {
        $this->billingName = $billingName;
        return $this;
    }

    public function getBillingAddress(): ?array
    {
        return $this->billingAddress ? json_decode($this->billingAddress, true) : null;
    }

    public function setBillingAddress(?array $billingAddress): static
    {
        $this->billingAddress = $billingAddress ? json_encode($billingAddress) : null;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTime
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTime $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata ? json_decode($this->metadata, true) : null;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata ? json_encode($metadata) : null;
        return $this;
    }

    /**
     * Get masked card number for display
     */
    public function getMaskedCardNumber(): string
    {
        if (!$this->last4Digits) {
            return '****';
        }
        return '**** **** **** ' . $this->last4Digits;
    }

    /**
     * Check if payment method is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiryMonth || !$this->expiryYear) {
            return false;
        }

        $expiryDate = \DateTime::createFromFormat('m/Y', $this->expiryMonth . '/' . $this->expiryYear);
        if (!$expiryDate) {
            return false;
        }

        // Set to last day of expiry month
        $expiryDate->modify('last day of this month');
        
        return $expiryDate < new \DateTime();
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->lastUsedAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
}