<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidentAttendance;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSessionScope;
use App\ChildrenMinistry\Incidents\Application\IncidentAttendanceRow;
use App\ChildrenMinistry\Incidents\Application\IncidentAttendanceView;
use App\ChildrenMinistry\Incidents\Application\IncidentVisit;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;

/** Who attended a session and when, so a report can name a real stretch of attendance. Identity and intervals only. */
final readonly class ListIncidentAttendanceHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private IncidentSessionScope $scope,
        private IncidentReader $reader,
        private PersonDirectory $people,
    ) {
    }

    public function handle(ListIncidentAttendanceQuery $query): IncidentAttendanceView
    {
        $manages = $this->authorization->hasPermission('children.incidents.manage');
        $this->authorization->requireActorPermission($query->actorId, $manages ? 'children.incidents.manage' : 'children.incidents.record');
        ChildrenRules::page($query->limit, $query->offset);
        if (!$manages) {
            $this->scope->require($query->actorId, $query->sessionId);
        }
        $term = ChildrenRules::optional($query->search, 120);
        $page = $this->reader->visits($query->sessionId, '' === $term ? null : $this->people->matchingIds($term), $query->limit, $query->offset);
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (IncidentVisit $visit): string => $visit->childId, $page->visits)))) as $person) {
            $names[$person->id] = $person->name;
        }

        return new IncidentAttendanceView(
            array_map(static fn (IncidentVisit $visit): IncidentAttendanceRow => new IncidentAttendanceRow($visit, $names[$visit->childId] ?? 'Unavailable person'), $page->visits),
            $page->total,
        );
    }
}
