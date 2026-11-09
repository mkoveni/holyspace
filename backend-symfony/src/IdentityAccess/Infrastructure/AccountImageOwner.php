<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Media\Application\Contract\ImageOwnership;
use Doctrine\DBAL\Connection;

/** An account's profile photo: the account itself, or an administrator with `identity.manage`. */
final readonly class AccountImageOwner implements ImageOwnership
{
    public function __construct(private PermissionChecker $permissions, private Connection $connection)
    {
    }

    public function ownerType(): string
    {
        return 'account';
    }

    public function canView(string $actorId, string $ownerId): bool
    {
        return $this->allowed($actorId, $ownerId);
    }

    public function canManage(string $actorId, string $ownerId): bool
    {
        return $this->allowed($actorId, $ownerId);
    }

    public function exists(string $ownerId): bool
    {
        return false !== $this->connection->fetchOne('SELECT id FROM user_accounts WHERE id = ? FOR SHARE', [$ownerId]);
    }

    private function allowed(string $actorId, string $ownerId): bool
    {
        return strtolower($actorId) === strtolower($ownerId) || $this->permissions->hasPermission('identity.manage');
    }
}
