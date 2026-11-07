<?php

declare(strict_types=1);

namespace App\Communications\Domain;

use DateTimeImmutable;

/**
 * Timing rules for device push jobs. A push is a nudge to open the inbox, so it is dropped after 24 hours rather than
 * delivered late, and a send that may have reached the provider is never repeated automatically.
 */
final class PushDeliveryPolicy
{
    public const int MAX_ATTEMPTS = 5;
    public const int RECEIPT_DELAY_SECONDS = 900;
    public const int LEASE_SECONDS = 120;

    private function __construct()
    {
    }

    /** Exponential backoff from 30 seconds, capped at one hour. */
    public static function retryAt(DateTimeImmutable $at, int $attempts): DateTimeImmutable
    {
        return $at->modify('+'.min(3600, 30 << min($attempts, 7)).' seconds');
    }

    public static function expired(DateTimeImmutable $created, DateTimeImmutable $at): bool
    {
        return $created->modify('+24 hours') <= $at;
    }

    public static function retryable(int $attempts): bool
    {
        return $attempts < self::MAX_ATTEMPTS;
    }
}
