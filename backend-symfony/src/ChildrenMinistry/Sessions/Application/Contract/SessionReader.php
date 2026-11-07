<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use DateTimeImmutable;

/** Read side of sessions, their staff and the workers who can serve. */
interface SessionReader
{
    public function ministrySession(string $id): ?MinistrySession;

    /**
     * Sessions overlapping [from, until), by start then id. A null limit returns all of them.
     *
     * @return list<MinistrySession>
     */
    public function sessions(?string $locationId, DateTimeImmutable $from, DateTimeImmutable $until, ?int $limit, int $offset): array;

    /**
     * Sessions in the window where the account's unrevoked worker is marked present.
     *
     * @return list<MinistrySession>
     */
    public function stationSessions(string $accountId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array;

    /** @return list<SessionStaff> ordered by worker id */
    public function staff(string $sessionId): array;

    public function worker(string $personId): ?Worker;

    public function workerByAccount(string $accountId): ?Worker;

    /** Whether the worker is marked present in another open session overlapping [from, until). */
    public function presentElsewhere(string $workerId, string $sessionId, DateTimeImmutable $from, DateTimeImmutable $until): bool;
}
