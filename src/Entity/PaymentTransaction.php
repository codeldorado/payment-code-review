<?php

namespace App\Entity;

use App\Repository\PaymentTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentTransactionRepository::class)]
#[ORM\Table(name: 'payment_transactions_tbl')]
class PaymentTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private ?string $uuid = null;

    #[ORM\Column(length: 255)]
    private ?string $transaction_id = null;

    #[ORM\Column(length: 255)]
    private ?string $_used_token = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $amount = null;

    #[ORM\Column(length: 50)]
    private ?string $currency_code = null;

    #[ORM\Column(length: 20)]
    private ?string $paymentStatus = null;

    #[ORM\Column(length: 4)]
    private ?string $last4Digits = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122(); // Generate a proper UUID v4
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getTransactionId(): ?string
    {
        return $this->transaction_id;
    }

    public function setTransactionId(string $transaction_id): static
    {
        $this->transaction_id = $transaction_id;

        return $this;
    }

    public function getUsedToken(): ?string
    {
        return $this->_used_token;
    }

    public function setUsedToken(?string $used_token): void
    {
        $this->_used_token = $used_token;
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

    public function getCurrencyCode(): ?string
    {
        return $this->currency_code;
    }

    public function setCurrencyCode(string $currency_code): static
    {
        $this->currency_code = $currency_code;

        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getLast4Digits(): ?string
    {
        return $this->last4Digits;
    }

    public function setLast4Digits(string $last4Digits): static
    {
        $this->last4Digits = $last4Digits;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}