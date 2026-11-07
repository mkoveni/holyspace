<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetStationRoster;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Sessions\Application\StationAttendance;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;

final readonly class GetStationRosterHandler
{
    public function __construct(private PermissionChecker $authorization, private StaffScope $scope, private AttendanceReader $attendance, private PersonDirectory $people)
    {
    }

    /** @return list<StationAttendance> */
    public function handle(GetStationRosterQuery $query): array
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $this->scope->require($query->actorId, $query->sessionId);
        ChildrenRules::page($query->limit, $query->offset);

        return array_map(function ($value): StationAttendance {
            $child = $this->people->get($value->childId);

            return new StationAttendance(AttendanceView::from($value), null === $child ? 'Child profile unavailable' : $child->name);
        }, $this->attendance->roster($query->sessionId, $query->openOnly, $query->limit, $query->offset));
    }
}
