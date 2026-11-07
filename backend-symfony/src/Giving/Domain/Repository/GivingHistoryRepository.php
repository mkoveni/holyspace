<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use DateTimeImmutable;

/** Append-only Giving audit trail (`giving_history`); the database rejects updates and deletes. */
interface GivingHistoryRepository
{
    public const string FUND = 'FUND';
    public const string GIFT = 'GIFT';

    /** @param self::FUND|self::GIFT $targetType */
    public function append(string $targetId, string $targetType, string $action, string $reason, string $actorId, DateTimeImmutable $at): void;
}
