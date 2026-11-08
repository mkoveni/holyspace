<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Shared\Application\CoverImage\CoverImageInfo;
use App\Shared\Domain\CoverSlot;

/** The staff view of an event's cover: its version and the stored image's metadata, if one is set. */
final readonly class EventCover
{
    public function __construct(public CoverSlot $slot, public ?CoverImageInfo $image)
    {
    }
}
