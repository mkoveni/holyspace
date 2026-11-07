<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetRoster;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetRosterHandler
{
    public function __construct(private PermissionChecker $authorization, private StaffScope $scope, private AttendanceReader $attendance)
    {
    }

    /** @return list<AttendanceView> */
    public function handle(GetRosterQuery $query): array
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $this->scope->require($query->actorId, $query->sessionId);
        ChildrenRules::page($query->limit, $query->offset);

        return array_map(AttendanceView::from(...), $this->attendance->roster($query->sessionId, $query->openOnly, $query->limit, $query->offset));
    }
}
