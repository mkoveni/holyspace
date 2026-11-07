<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalGivingHistory implements GivingHistoryRepository
{
    public function __construct(private Connection $connection, private IdGenerator $ids)
    {
    }

    public function append(string $targetId, string $targetType, string $action, string $reason, string $actorId, DateTimeImmutable $at): void
    {
        $this->connection->insert('giving_history', [
            'id' => $this->ids->uuid(),
            'target_id' => $targetId,
            'target_type' => $targetType,
            'action' => $action,
            'reason' => $reason,
            'actor_id' => $actorId,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }
}
