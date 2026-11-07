<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidents;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Incidents\Application\IncidentListView;
use App\ChildrenMinistry\Incidents\Application\IncidentRow;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;

/** Metadata only: no descriptions, actions or notes. */
final readonly class ListIncidentsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private IncidentReader $reader,
        private PersonDirectory $people,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(ListIncidentsQuery $query): IncidentListView
    {
        $this->authorization->requirePermission('children.incidents.manage');
        ChildrenRules::page($query->limit, $query->offset);
        if (!\in_array($query->status, ['ALL', 'ACTIVE', 'OPEN', 'IN_REVIEW', 'CLOSED'], true)) {
            throw ChildrenException::invalid('Choose a valid incident status.');
        }
        $page = $this->reader->list($query->sessionId, $query->childId, $query->status, $query->overdueOnly, $this->calendar->today(), $query->limit, $query->offset);
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (IncidentSummary $incident): string => $incident->childId, $page->incidents)))) as $person) {
            $names[$person->id] = $person->name;
        }

        return new IncidentListView(
            array_map(static fn (IncidentSummary $incident): IncidentRow => new IncidentRow($incident, $names[$incident->childId] ?? 'Unavailable person'), $page->incidents),
            $page->total,
            $page->overdue,
            $page->unassigned,
        );
    }
}
