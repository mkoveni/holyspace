<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\Tests\ChildrenMinistry\Shared\CallLog;

/** Delegates to the fixture but records which row each lock names, so lock order can be asserted. */
final readonly class IdRecordingSessions implements SessionRepository
{
    public function __construct(private SessionRepository $inner, private CallLog $log)
    {
    }

    public function ministrySessionForUpdate(string $id): ?MinistrySession
    {
        $this->log->entries[] = 'lock:session:'.$id;

        return $this->inner->ministrySessionForUpdate($id);
    }

    public function insertMinistrySession(MinistrySession $session): void
    {
        $this->inner->insertMinistrySession($session);
    }

    public function updateMinistrySession(MinistrySession $session): void
    {
        $this->inner->updateMinistrySession($session);
    }

    public function workerForUpdate(string $personId): ?Worker
    {
        $this->log->entries[] = 'lock:worker:'.$personId;

        return $this->inner->workerForUpdate($personId);
    }

    public function insertWorker(Worker $worker): void
    {
        $this->inner->insertWorker($worker);
    }

    public function updateWorker(Worker $worker): void
    {
        $this->inner->updateWorker($worker);
    }

    public function saveSessionStaff(SessionStaff $staff): void
    {
        $this->inner->saveSessionStaff($staff);
    }
}
