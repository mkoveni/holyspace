<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListSessions;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\SessionView;
use App\ChildrenMinistry\Sessions\Application\SessionWindow;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

final readonly class ListSessionsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionReader $sessions,
        private SessionOperations $operations,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<SessionView> */
    public function handle(ListSessionsQuery $query): array
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);
        $now = $this->clock->now();
        [$from, $until] = SessionWindow::resolve($query->from, $query->until, $now);

        return array_map(
            fn ($session): SessionView => $this->operations->view($session, $now),
            $this->sessions->sessions($query->locationId, $from, $until, $query->limit, $query->offset),
        );
    }
}
