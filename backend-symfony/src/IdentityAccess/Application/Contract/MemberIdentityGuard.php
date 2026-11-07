<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface MemberIdentityGuard
{
    /** Locks the caller's active member link, then returns their verified person id. */
    public function lockPerson(string $actorId): string;
}
