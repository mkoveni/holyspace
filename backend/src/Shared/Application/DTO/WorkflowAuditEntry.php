<?php

declare(strict_types=1);

namespace App\Shared\Application\DTO;

final readonly class WorkflowAuditEntry
{
    public function __construct(public string $id, public string $recordId, public string $kind, public string $action, public int $version, public ?string $actorId, public ?string $actorName, public string $occurredAt)
    {
    }
}
