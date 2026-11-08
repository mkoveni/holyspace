<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ChangeEventCover;

use App\Shared\Application\Command;
use App\Shared\Domain\CoverImage;

/**
 * Sets or (with a null image) removes an event's cover picture.
 *
 * @implements Command<null>
 */
final readonly class ChangeEventCoverCommand implements Command
{
    public function __construct(public string $actorId, public string $eventId, public ?CoverImage $image, public int $expectedVersion)
    {
    }
}
