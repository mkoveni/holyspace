<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Domain\IdentityException;

/** In-memory test double: no database, no identity context. */
final class AllowedPermissions implements PermissionChecker
{
    /** @param list<string> $granted */
    public function __construct(private readonly array $granted)
    {
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->granted, true);
    }

    public function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw IdentityException::forbidden('Permission denied.');
        }
    }

    public function requireActorPermission(string $claimedActorId, string $permission): void
    {
        $this->requirePermission($permission);
    }
}
