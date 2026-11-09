<?php

declare(strict_types=1);

namespace App\People\Infrastructure;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Media\Application\Contract\ImageOwnership;
use Doctrine\DBAL\Connection;

/** A person's photo: staff who read people may see it, staff who manage people may change it. */
final readonly class PersonImageOwner implements ImageOwnership
{
    public function __construct(private PermissionChecker $permissions, private Connection $connection)
    {
    }

    public function ownerType(): string
    {
        return 'person';
    }

    public function canView(string $actorId, string $ownerId): bool
    {
        return $this->permissions->hasPermission('people.read');
    }

    public function canManage(string $actorId, string $ownerId): bool
    {
        return $this->permissions->hasPermission('people.manage');
    }

    public function exists(string $ownerId): bool
    {
        return false !== $this->connection->fetchOne('SELECT id FROM people_persons WHERE id = ? FOR SHARE', [$ownerId]);
    }
}
