<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetPickupCollectors;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\PickupCollector;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use Psr\Clock\ClockInterface;

/** Adults with a current grant for this child in this programme. Checkout repeats every check. */
final readonly class GetPickupCollectorsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private StaffScope $scope,
        private AttendanceReader $attendance,
        private SessionReader $sessions,
        private ParticipationReader $participation,
        private SafeguardingReader $safeguarding,
        private ParticipantAccess $people,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return list<PickupCollector> */
    public function handle(GetPickupCollectorsQuery $query): array
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        $value = $this->attendance->attendance($query->attendanceId) ?? throw ChildrenException::missing('Attendance');
        $this->scope->require($query->actorId, $value->sessionId);
        $session = $this->sessions->ministrySession($value->sessionId) ?? throw ChildrenException::missing('Session');
        $group = $this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class');
        $now = $this->clock->now();
        $today = $this->calendar->today();

        $collectors = [];
        foreach ($this->safeguarding->authorizations($value->childId) as $grant) {
            if ($grant->programmeId !== $group->programmeId || null !== $grant->revokedAt || !ChildrenRules::covers($grant->validFrom, $grant->validUntil, $now, $now)) {
                continue;
            }
            $authority = $this->safeguarding->guardianAuthority($grant->authorityId);
            if (null === $authority || null !== $authority->revokedAt || !ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now)) {
                continue;
            }
            $collector = $this->people->person($grant->collectorId);
            if (!$collector->available || null === $collector->birthDate || $collector->birthDate->plusYears(18)->isAfter($today)) {
                continue;
            }
            $collectors[] = new PickupCollector($grant->id, $collector->id, $collector->name, $grant->validUntil);
        }

        return $collectors;
    }
}
