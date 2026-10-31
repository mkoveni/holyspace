<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\ListFollowupAssignees;

use App\Shared\Application\CQRS\Query;

final readonly class ListFollowupAssigneesQuery implements Query
{
    public function __construct(public ?string $segmentId, public ?string $taskId = null)
    {
    }
}
