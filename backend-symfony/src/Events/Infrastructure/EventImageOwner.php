<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Media\Application\Contract\ImageOwnership;
use Doctrine\DBAL\Connection;

/** An event's cover and banner: staff who read events may see them, staff who manage events may change them. */
final readonly class EventImageOwner implements ImageOwnership
{
    public function __construct(private PermissionChecker $permissions, private Connection $connection)
    {
    }

    public function ownerType(): string
    {
        return 'event';
    }

    public function canView(string $actorId, string $ownerId): bool
    {
        return $this->permissions->hasPermission('events.read');
    }

    public function canManage(string $actorId, string $ownerId): bool
    {
        return $this->permissions->hasPermission('events.manage');
    }

    public function exists(string $ownerId): bool
    {
        return false !== $this->connection->fetchOne('SELECT id FROM event_definitions WHERE id = ? FOR SHARE', [$ownerId]);
    }
}
