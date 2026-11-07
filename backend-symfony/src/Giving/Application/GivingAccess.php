<?php

declare(strict_types=1);

namespace App\Giving\Application;

use App\IdentityAccess\Application\Contract\PermissionChecker;

/**
 * Giving permissions. Financial access is never inferred from People or leadership permissions, and writes also
 * prove the claimed actor is the trusted caller.
 */
final readonly class GivingAccess
{
    public function __construct(private PermissionChecker $permissions)
    {
    }

    public function read(): void
    {
        $this->permissions->requirePermission('giving.read');
    }

    public function manage(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'giving.manage');
    }

    public function reverse(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'giving.reverse');
    }

    public function acknowledge(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'giving.acknowledge');
    }

    public function reconcile(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'giving.reconcile');
    }

    public function export(): void
    {
        $this->read();
        $this->permissions->requirePermission('giving.export');
    }
}
