<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\AccessIncident;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\IncidentAccess;
use App\ChildrenMinistry\Incidents\Application\IncidentAccessView;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Opening an incident's private details is a write: every successful access, including each page of history, is audited. */
final readonly class AccessIncidentHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private IncidentReader $reader,
        private IncidentAccess $access,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(AccessIncidentCommand $command): IncidentAccessView
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.incidents.manage');
        ChildrenRules::page($command->limit, $command->offset);

        $incident = $this->access->current($command->incidentId, $this->calendar->today());
        $details = $this->reader->details($incident->id) ?? throw ChildrenException::missing('Current incident details');
        $events = $this->reader->events($incident->id, $command->limit, $command->offset);
        $total = $this->reader->eventCount($incident->id);
        $this->audit->record($command->actorId, 'incident.accessed', $incident->id, '', $this->clock->now());

        return new IncidentAccessView($incident, $details, $events, $total);
    }
}
