<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventAttendanceHistory;

use App\Events\Application\Contract\AttendanceHistoryEntry;
use App\Events\Application\Contract\AttendanceReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventAttendanceHistoryHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReader $attendance)
    {
    }

    /** @return list<AttendanceHistoryEntry> */
    public function handle(GetEventAttendanceHistoryQuery $query): array
    {
        $this->authorization->requirePermission('events.attendance.read');
        EventRules::page($query->limit, $query->offset);

        return $this->attendance->history($query->registrationId, $query->limit, $query->offset) ?? throw EventException::missing('Attendance');
    }
}
