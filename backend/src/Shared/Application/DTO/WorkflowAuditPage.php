<?php

declare(strict_types=1);

namespace App\Shared\Application\DTO;

final readonly class WorkflowAuditPage
{
    /** @param list<WorkflowAuditEntry> $entries */
    public function __construct(public array $entries, public ?int $nextBefore)
    {
    }
}
