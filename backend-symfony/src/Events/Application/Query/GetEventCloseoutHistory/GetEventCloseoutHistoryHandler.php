<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventCloseoutHistory;

use App\Events\Application\Contract\CloseoutHistoryEntry;
use App\Events\Application\EventReadiness;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventCloseoutHistoryHandler
{
    public function __construct(private PermissionChecker $authorization, private EventReadiness $readiness)
    {
    }

    /** @return list<CloseoutHistoryEntry> */
    public function handle(GetEventCloseoutHistoryQuery $query): array
    {
        $this->authorization->requirePermission('events.manage');

        return $this->readiness->history($query->occurrenceId, $query->limit, $query->offset);
    }
}
