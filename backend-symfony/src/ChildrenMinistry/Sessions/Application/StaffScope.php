<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Station staff may act only on sessions where they are present; supervisors may act on any. */
final readonly class StaffScope
{
    public function __construct(private PermissionChecker $authorization, private SessionReader $sessions)
    {
    }

    public function require(string $actorId, string $sessionId): void
    {
        if ($this->authorization->hasPermission('children.supervise')) {
            return;
        }
        $worker = $this->sessions->workerByAccount($actorId);
        if (null === $worker || null !== $worker->revokedAt) {
            throw ChildrenException::forbidden();
        }
        foreach ($this->sessions->staff($sessionId) as $staff) {
            if ($staff->workerId === $worker->personId && $staff->present) {
                return;
            }
        }

        throw ChildrenException::forbidden();
    }
}
