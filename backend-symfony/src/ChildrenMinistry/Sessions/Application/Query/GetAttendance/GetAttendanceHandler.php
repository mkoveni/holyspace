<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetAttendance;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetAttendanceHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReader $attendance, private StaffScope $scope)
    {
    }

    public function handle(GetAttendanceQuery $query): AttendanceView
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $value = $this->attendance->attendance($query->attendanceId) ?? throw ChildrenException::missing('Attendance');
        $this->scope->require($query->actorId, $value->sessionId);

        return AttendanceView::from($value);
    }
}
