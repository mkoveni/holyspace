<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalCommunicationAudit implements CommunicationAudit
{
    public function __construct(private Connection $connection, private IdGenerator $ids)
    {
    }

    public function record(string $actor, string $action, string $target, string $detail, DateTimeImmutable $at): void
    {
        $this->connection->insert('communication_audit', [
            'id' => $this->ids->uuid(),
            'actor_id' => $actor,
            'action' => $action,
            'target_id' => $target,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }
}
