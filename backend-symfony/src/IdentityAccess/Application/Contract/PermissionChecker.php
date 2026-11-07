<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/** Published access policy for other bounded contexts. Always evaluates the trusted current caller. */
interface PermissionChecker
{
    public function hasPermission(string $permission): bool;

    public function requirePermission(string $permission): void;

    /** Also requires the claimed actor id to be the trusted caller; an administrator id is not proof of authority. */
    public function requireActorPermission(string $claimedActorId, string $permission): void;
}
