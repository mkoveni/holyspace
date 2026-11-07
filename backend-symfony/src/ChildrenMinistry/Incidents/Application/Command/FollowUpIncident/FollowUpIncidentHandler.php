<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\FollowUpIncident;

use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\ChildrenMinistry\Incidents\Domain\IncidentEventType;
use App\ChildrenMinistry\Incidents\Domain\Repository\IncidentRepository;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Triage, action, guardian contact, closure and reopening, each a versioned step with a private note. Lock order: the
 * incident, then (for a guardian contact) the guardian authority. The ordinary audit row carries no note.
 */
final readonly class FollowUpIncidentHandler implements CommandHandler
{
    private const string AUTHORITY_INVALID = 'Choose current verified guardian authority.';

    public function __construct(
        private PermissionChecker $authorization,
        private IncidentRepository $repository,
        private SafeguardingReader $safeguarding,
        private SafeguardingRepository $guardianLocks,
        private ResponsibleAccountDirectory $accounts,
        private PersonDirectory $people,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
    ) {
    }

    public function handle(FollowUpIncidentCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.incidents.manage');
        $note = ChildrenRules::text($command->note, 3000);
        $action = null === $command->action ? null : IncidentEventType::tryFrom($command->action);
        if (null === $action || IncidentEventType::Reported === $action) {
            throw ChildrenException::invalid('Choose an incident follow-up action.');
        }

        $current = $this->repository->lock($command->incidentId) ?? throw ChildrenException::missing('Incident');
        if (IncidentEventType::GuardianContact === $action) {
            if (null === $command->guardianAuthorityId) {
                throw ChildrenException::invalid(self::AUTHORITY_INVALID);
            }
            $this->guardianLocks->guardianAuthorityForUpdate($command->guardianAuthorityId) ?? throw ChildrenException::invalid(self::AUTHORITY_INVALID);
        }
        $now = $this->clock->now();
        $on = $this->calendar->today();
        if ($current->retainUntil->isBefore($on)) {
            throw ChildrenException::missing('Current incident');
        }
        if (IncidentEventType::Triaged === $action) {
            if (null === $command->ownerId || !$this->accounts->eligible($command->ownerId, 'children.incidents.manage')) {
                throw ChildrenException::invalid('Choose an active account with incident-management access.');
            }
            $changed = $current->triage($command->expectedVersion, $command->ownerId, $command->reviewOn, $on);
        } else {
            $changed = $current->progress($command->expectedVersion, $action, $on);
        }

        $guardian = null;
        if (IncidentEventType::GuardianContact === $action) {
            $authority = $this->safeguarding->guardianAuthority((string) $command->guardianAuthorityId);
            if (null === $authority || $authority->childId !== $current->childId || null !== $authority->revokedAt || !ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now)) {
                throw ChildrenException::invalid(self::AUTHORITY_INVALID);
            }
            $person = $this->people->get($authority->guardianId);
            if (null === $person || !$person->available) {
                throw ChildrenException::invalid('Guardian identity is unavailable.');
            }
            ChildrenRules::adult($person->birthDate, $on);
            $guardian = $person->id;
        }

        $this->repository->update($changed);
        $this->repository->append(new IncidentEvent($this->ids->uuid(), $changed->id, $changed->version, $action, $command->actorId, $now, $note, $guardian));
        $this->audit->record($command->actorId, 'incident.followup.'.strtolower($action->value), $changed->id, '', $now);

        return null;
    }
}
