<?php

namespace App\Enum;

/**
 * Payment status constants for consistent status handling across the application
 */
enum PaymentStatus: string
{
    case APPROVED = 'Approved';
    case DECLINED = 'Declined';
    case FAILED = 'Failed';
    case PENDING = 'Pending';
    case REFUNDED = 'Refunded';
    case PARTIALLY_REFUNDED = 'Partially Refunded';
    case CANCELLED = 'Cancelled';

    /**
     * Get all available payment statuses
     */
    public static function getAll(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Check if status is successful
     */
    public function isSuccessful(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Check if status is final (no further processing possible)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REFUNDED,
            self::CANCELLED,
            self::FAILED
        ]);
    }
}