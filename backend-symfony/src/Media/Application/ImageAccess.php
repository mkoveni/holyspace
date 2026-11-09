<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Media\Domain\MediaException;
use App\Media\Domain\SlotKey;

/** Permission first (the owner decides), then whether the owner exists: a caller without access learns nothing. */
final readonly class ImageAccess
{
    public function __construct(private ImageOwners $owners)
    {
    }

    public function manage(string $actorId, SlotKey $key): void
    {
        $owner = $this->owners->for($key->ownerType);
        if (!$owner->canManage($actorId, $key->ownerId)) {
            throw MediaException::forbidden();
        }
        if (!$owner->exists($key->ownerId)) {
            throw MediaException::missing();
        }
    }

    public function view(string $actorId, SlotKey $key): void
    {
        $owner = $this->owners->for($key->ownerType);
        if (!$owner->canView($actorId, $key->ownerId) && !$owner->canManage($actorId, $key->ownerId)) {
            throw MediaException::forbidden();
        }
        if (!$owner->exists($key->ownerId)) {
            throw MediaException::missing();
        }
    }
}
