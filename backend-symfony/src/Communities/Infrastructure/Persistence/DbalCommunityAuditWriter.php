<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityAuditWriter;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalCommunityAuditWriter implements CommunityAuditWriter
{
    public function __construct(private Connection $connection)
    {
    }

    public function record(
        string $communityId,
        string $targetId,
        string $action,
        string $reason,
        string $actorId,
        DateTimeImmutable $at,
    ): void {
        $this->connection->insert('community_history', [
            'id' => Uuid::v4()->toRfc4122(), 'community_id' => $communityId, 'target_id' => $targetId,
            'action' => $action, 'reason' => $reason, 'actor_id' => $actorId,
            'occurred_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
