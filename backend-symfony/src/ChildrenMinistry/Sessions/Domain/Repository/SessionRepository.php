<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain\Repository;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\Worker;

/** Write side of sessions, their staff and the workers who can serve. */
interface SessionRepository
{
    public function ministrySessionForUpdate(string $id): ?MinistrySession;

    public function insertMinistrySession(MinistrySession $session): void;

    /** Updates the row only if its stored version is one less than the session's; otherwise a 409. */
    public function updateMinistrySession(MinistrySession $session): void;

    public function workerForUpdate(string $personId): ?Worker;

    public function insertWorker(Worker $worker): void;

    /** Updates the row only if its stored version is one less than the worker's; otherwise a 409. */
    public function updateWorker(Worker $worker): void;

    /** Adds the worker to the session or changes their role and presence. */
    public function saveSessionStaff(SessionStaff $staff): void;
}
