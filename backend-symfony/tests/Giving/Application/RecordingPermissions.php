<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Domain\IdentityException;

/** In-memory test double: grants a fixed set of permissions to one trusted caller and records every check. */
final class RecordingPermissions implements PermissionChecker
{
    /** @var list<string> */
    public array $checks = [];

    /** @param list<string> $granted */
    public function __construct(private readonly string $caller, private readonly array $granted)
    {
    }

    public function hasPermission(string $permission): bool
    {
        return \in_array($permission, $this->granted, true);
    }

    public function requirePermission(string $permission): void
    {
        $this->checks[] = $permission;
        $this->grant($permission);
    }

    public function requireActorPermission(string $claimedActorId, string $permission): void
    {
        $this->checks[] = $claimedActorId.':'.$permission;
        if ($claimedActorId !== $this->caller) {
            throw IdentityException::forbidden('Permission denied.');
        }
        $this->grant($permission);
    }

    private function grant(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw IdentityException::forbidden('Permission denied.');
        }
    }
}
