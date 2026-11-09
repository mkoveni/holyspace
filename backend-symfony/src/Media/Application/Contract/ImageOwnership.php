<?php

declare(strict_types=1);

namespace App\Media\Application\Contract;

/**
 * Implemented by the context that owns a kind of owner (People for `person`, Events for `event`, ...) and tagged
 * `media.image_owners`. Media decides nothing about who may touch an owner's pictures; it asks the owner.
 */
interface ImageOwnership
{
    /** The `OwnerType` value this implementation answers for, such as `person`. */
    public function ownerType(): string;

    public function canView(string $actorId, string $ownerId): bool;

    public function canManage(string $actorId, string $ownerId): bool;

    /**
     * Whether the owner exists. It takes a share lock on the owner row for the rest of the transaction, so an owner
     * cannot be deleted between this check and the picture being saved (lock order: owner, then slot).
     */
    public function exists(string $ownerId): bool;
}
