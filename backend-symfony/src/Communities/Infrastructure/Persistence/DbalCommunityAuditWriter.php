<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalCommunityAuditWriter implements CommunityAuditWriter
{
    public function __construct(private Connection $connection, private IdGenerator $ids)
    {
    }

    public function record(string $communityId, string $targetId, string $action, string $reason, string $actorId, DateTimeImmutable $at): void
    {
        $this->connection->insert('community_history', [
            'id' => $this->ids->uuid(),
            'community_id' => $communityId,
            'target_id' => $targetId,
            'action' => $action,
            'reason' => $reason,
            'actor_id' => $actorId,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }
}
