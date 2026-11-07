<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetChildAttendance;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** A child's visits, newest first. Java keeps this handler in its reporting package; here it lives with attendance. */
final readonly class GetChildAttendanceHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReader $attendance)
    {
    }

    /** @return list<AttendanceView> */
    public function handle(GetChildAttendanceQuery $query): array
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);

        return array_map(AttendanceView::from(...), $this->attendance->childAttendance($query->childId, $query->limit, $query->offset));
    }
}
