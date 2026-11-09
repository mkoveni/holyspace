<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Media\Application\Contract\ImageOwnership;
use Doctrine\DBAL\Connection;

/** A group's or ministry's picture: readers and leaders may see it, only ministry managers may change it. */
final readonly class CommunityImageOwner implements ImageOwnership
{
    public function __construct(private PermissionChecker $permissions, private Connection $connection)
    {
    }

    public function ownerType(): string
    {
        return 'community';
    }

    public function canView(string $actorId, string $ownerId): bool
    {
        return $this->permissions->hasPermission('ministries.read') || $this->permissions->hasPermission('ministries.lead');
    }

    public function canManage(string $actorId, string $ownerId): bool
    {
        return $this->permissions->hasPermission('ministries.manage');
    }

    public function exists(string $ownerId): bool
    {
        return false !== $this->connection->fetchOne('SELECT id FROM communities WHERE id = ? FOR SHARE', [$ownerId]);
    }
}
