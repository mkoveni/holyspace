<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceEvents;

use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetAttendanceEventsHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReader $attendance)
    {
    }

    /** @return list<AttendanceEvent> */
    public function handle(GetAttendanceEventsQuery $query): array
    {
        $this->authorization->requirePermission('children.supervise');
        ChildrenRules::page($query->limit, $query->offset);

        return $this->attendance->events($query->attendanceId, $query->limit, $query->offset);
    }
}
