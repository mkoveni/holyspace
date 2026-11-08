<?php

declare(strict_types=1);

namespace App\Shared\Application\CoverImage;

use App\Shared\Application\IdGenerator;
use App\Shared\Domain\CoverImage;
use App\Shared\Domain\CoverSlot;
use DateTimeImmutable;

/**
 * The storage half of changing a cover, run by the owning context's handler after its permission check, its lock and
 * its version check, inside the command transaction: store the new bytes under a fresh id, let the caller save the
 * pointer, then discard the bytes it replaced.
 */
final readonly class CoverImages
{
    public function __construct(private CoverImageStore $store, private IdGenerator $ids)
    {
    }

    /** Stores the upload (if any) and returns the slot that replaces `$current`. A null image clears the cover. */
    public function next(CoverSlot $current, ?CoverImage $image, DateTimeImmutable $at): CoverSlot
    {
        if (null === $image) {
            return new CoverSlot(null, $current->version + 1);
        }
        $id = $this->ids->uuid();
        $this->store->add($id, $image, $at);

        return new CoverSlot($id, $current->version + 1);
    }

    /** Removes the replaced bytes once no pointer refers to them. */
    public function discard(CoverSlot $previous): void
    {
        if (null !== $previous->imageId) {
            $this->store->remove($previous->imageId);
        }
    }

    public function info(CoverSlot $slot): ?CoverImageInfo
    {
        return null === $slot->imageId ? null : $this->store->info($slot->imageId);
    }

    public function content(CoverSlot $slot): ?CoverImageContent
    {
        return null === $slot->imageId ? null : $this->store->content($slot->imageId);
    }
}
