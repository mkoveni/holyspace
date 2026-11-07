<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidentGuardians;

use App\ChildrenMinistry\Incidents\Application\IncidentAccess;
use App\ChildrenMinistry\Incidents\Application\IncidentGuardian;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use Psr\Clock\ClockInterface;

/** The guardians who may be contacted about an incident: current verified authority and an available adult. Names only. */
final readonly class ListIncidentGuardiansHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private IncidentAccess $access,
        private SafeguardingReader $safeguarding,
        private PersonDirectory $people,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return list<IncidentGuardian> */
    public function handle(ListIncidentGuardiansQuery $query): array
    {
        $this->authorization->requirePermission('children.incidents.manage');
        $now = $this->clock->now();
        $on = $this->calendar->today();
        $incident = $this->access->current($query->incidentId, $on);
        $authorities = array_values(array_filter(
            $this->safeguarding->authorities($incident->childId),
            static fn ($authority): bool => null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now),
        ));
        $people = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn ($authority): string => $authority->guardianId, $authorities)))) as $person) {
            $people[$person->id] = $person;
        }

        $guardians = [];
        foreach ($authorities as $authority) {
            $person = $people[$authority->guardianId] ?? null;
            if (null !== $person && $person->available && null !== $person->birthDate && !$person->birthDate->plusYears(18)->isAfter($on)) {
                $guardians[] = new IncidentGuardian($authority->id, $authority->guardianId, $person->name);
            }
        }

        return $guardians;
    }
}
