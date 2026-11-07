<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\PurgeExpiredIncidents;

use App\ChildrenMinistry\Incidents\Domain\Repository\IncidentRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Retention is enforced only by an explicit, bounded, audited call. GET requests never purge. */
final readonly class PurgeExpiredIncidentsHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private IncidentRepository $repository,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(PurgeExpiredIncidentsCommand $command): int
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.incidents.manage');
        ChildrenRules::page($command->limit, 0);

        $count = $this->repository->purgeExpired($this->calendar->today(), $command->limit);
        $this->audit->record($command->actorId, 'incident.details.purged', $command->actorId, (string) $count, $this->clock->now());

        return $count;
    }
}
