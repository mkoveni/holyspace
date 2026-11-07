<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

/**
 * Whether a staff member may see a child's support record from inside a session: they must be assigned to the
 * session and the child enrolled in its class or still checked in. Owned by Safeguarding, implemented by Sessions.
 */
interface SupportSessionScope
{
    /** @throws \App\ChildrenMinistry\Shared\Domain\ChildrenException when access is not allowed */
    public function require(string $actorId, string $sessionId, string $childId): void;
}
