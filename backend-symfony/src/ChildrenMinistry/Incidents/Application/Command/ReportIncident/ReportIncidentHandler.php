<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\ReportIncident;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSessionScope;
use App\ChildrenMinistry\Incidents\Domain\Incident;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\ChildrenMinistry\Incidents\Domain\IncidentEventType;
use App\ChildrenMinistry\Incidents\Domain\Repository\IncidentRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\RequestFingerprint;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Records an incident. Staff need the record permission and a place on the session; managers may report on any. Lock
 * order: the child's person row, then the session. A retry with the same request id returns the same incident, and a
 * changed payload under that id is a conflict. The ordinary audit row carries no description.
 */
final readonly class ReportIncidentHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private IncidentSessionScope $scope,
        private IncidentReader $reader,
        private IncidentRepository $repository,
        private SessionRepository $sessions,
        private PersonDirectory $people,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the incident id */
    public function handle(ReportIncidentCommand $command): string
    {
        $manages = $this->authorization->hasPermission('children.incidents.manage');
        $this->authorization->requireActorPermission($command->actorId, $manages ? 'children.incidents.manage' : 'children.incidents.record');
        $details = new IncidentDetails($command->description, $command->immediateAction);
        $hash = RequestFingerprint::of(
            $command->childId,
            $command->sessionId,
            $command->segmentId,
            $command->category,
            $command->severity,
            $command->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u'),
            $details->description,
            $details->immediateAction,
            $command->reviewOn->iso(),
            $command->retainUntil->iso(),
        );

        $this->people->lock($command->childId) ?? throw ChildrenException::missing('Child');
        $this->sessions->ministrySessionForUpdate($command->sessionId) ?? throw ChildrenException::missing('Session');
        if (!$manages) {
            $this->scope->require($command->actorId, $command->sessionId);
        }
        $previous = $this->reader->receipt($command->actorId, $command->requestId);
        if (null !== $previous) {
            if ($previous->requestHash !== $hash) {
                throw ChildrenException::conflict('This report request was already used for different details.');
            }

            return $previous->id;
        }

        $now = $this->clock->now();
        $today = $this->calendar->today();
        if ($command->occurredAt > $now || !$this->reader->attended($command->childId, $command->sessionId, $command->segmentId, $command->occurredAt)) {
            throw ChildrenException::invalid("The incident time must fall within this child's recorded attendance in the selected session.");
        }
        if ($command->reviewOn->isBefore($today) || $command->retainUntil->isBefore($today)) {
            throw ChildrenException::invalid('Review and retention dates must be current or future dates.');
        }
        $incident = Incident::report($this->ids->uuid(), $command->childId, $command->sessionId, $command->segmentId, $command->category, $command->severity, $command->occurredAt, $command->actorId, $now, $command->reviewOn, $command->retainUntil);
        $this->repository->insert($incident, $details, $command->requestId, $hash);
        $this->repository->append(new IncidentEvent($this->ids->uuid(), $incident->id, 0, IncidentEventType::Reported, $command->actorId, $now, 'Incident recorded.', null));
        $this->audit->record($command->actorId, 'incident.reported', $incident->id, '', $now);

        return $incident->id;
    }
}
