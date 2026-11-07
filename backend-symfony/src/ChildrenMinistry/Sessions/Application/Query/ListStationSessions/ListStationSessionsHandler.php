<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListStationSessions;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\SessionView;
use App\ChildrenMinistry\Sessions\Application\SessionWindow;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

/** A station user sees the sessions where they are present; a supervisor sees every session. */
final readonly class ListStationSessionsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionReader $sessions,
        private SessionOperations $operations,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<SessionView> */
    public function handle(ListStationSessionsQuery $query): array
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.station');
        ChildrenRules::page($query->limit, $query->offset);
        $now = $this->clock->now();
        [$from, $until] = SessionWindow::resolve($query->from, $query->until, $now);
        $found = $this->authorization->hasPermission('children.supervise')
            ? $this->sessions->sessions(null, $from, $until, $query->limit, $query->offset)
            : $this->sessions->stationSessions($query->actorId, $from, $until, $query->limit, $query->offset);

        return array_map(fn ($session): SessionView => $this->operations->view($session, $now), $found);
    }
}
