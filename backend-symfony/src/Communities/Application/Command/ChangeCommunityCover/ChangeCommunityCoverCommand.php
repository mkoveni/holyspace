<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\ChangeCommunityCover;

use App\Shared\Application\Command;
use App\Shared\Domain\CoverImage;

/**
 * Sets or (with a null image) removes a group's or ministry's cover picture.
 *
 * @implements Command<null>
 */
final readonly class ChangeCommunityCoverCommand implements Command
{
    public function __construct(public string $actorId, public string $communityId, public ?CoverImage $image, public int $expectedVersion)
    {
    }
}
