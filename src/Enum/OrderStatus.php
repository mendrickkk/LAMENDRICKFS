<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Order status values — keep in sync with mobile app filters.
 *
 * Active tab (mobile):    pending, processing
 * Completed tab (mobile): completed, delivered, cancelled
 */
final class OrderStatus
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

  /** @var list<string> */
    public const ALL = [
        self::PENDING,
        self::PROCESSING,
        self::COMPLETED,
        self::DELIVERED,
        self::CANCELLED,
    ];

  /** @var list<string> */
    public const ACTIVE_MOBILE = [self::PENDING, self::PROCESSING];

  /** @var list<string> */
    public const COMPLETED_MOBILE = [self::COMPLETED, self::DELIVERED, self::CANCELLED];

    /** @return array<string, string> label => value */
    public static function choices(): array
    {
        return [
            'Pending' => self::PENDING,
            'Processing' => self::PROCESSING,
            'Completed' => self::COMPLETED,
            'Delivered' => self::DELIVERED,
            'Cancelled' => self::CANCELLED,
        ];
    }

    public static function isValid(string $status): bool
    {
        return \in_array($status, self::ALL, true);
    }
}
