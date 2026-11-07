<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use DateTimeImmutable;

/** Append-only Giving audit trail (`giving_history`); the database rejects updates and deletes. */
interface GivingHistoryRepository
{
    public const string FUND = 'FUND';
    public const string GIFT = 'GIFT';
    public const string PLEDGE = 'PLEDGE';
    public const string BATCH = 'BATCH';

    /** @param self::FUND|self::GIFT|self::PLEDGE|self::BATCH $targetType */
    public function append(string $targetId, string $targetType, string $action, string $reason, string $actorId, DateTimeImmutable $at): void;
}
