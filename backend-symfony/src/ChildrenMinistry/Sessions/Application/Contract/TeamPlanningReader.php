<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Application\PlanningFacts;
use App\ChildrenMinistry\Sessions\Application\PlanningSession;
use App\ChildrenMinistry\Sessions\Application\PlanningWorkerPage;
use DateTimeImmutable;

/** Read side of team planning. Lists are bounded: 1001 upcoming sessions, 25 by id. */
interface TeamPlanningReader
{
    /**
     * Sessions starting in [from, until) and after now that are not closed or cancelled, by start then id.
     *
     * @return list<PlanningSession>
     */
    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until, ?string $programmeId, ?string $locationId, DateTimeImmutable $now): array;

    /**
     * @param list<string> $ids
     *
     * @return list<PlanningSession>
     */
    public function sessions(array $ids): array;

    /**
     * @param list<PlanningSession> $sessions
     * @param list<string>          $extraWorkers
     */
    public function facts(array $sessions, array $extraWorkers): PlanningFacts;

    /** @param list<string>|null $matchingIds null for every worker; an empty list matches none */
    public function workers(?array $matchingIds, int $limit, int $offset): PlanningWorkerPage;
}
