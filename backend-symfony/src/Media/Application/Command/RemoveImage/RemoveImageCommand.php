<?php

declare(strict_types=1);

namespace App\Media\Application\Command\RemoveImage;

use App\Media\Domain\ImageKind;
use App\Shared\Application\Command;

/**
 * Clears one owner's picture. The slot keeps a higher version so a stale request cannot bring the old picture back.
 *
 * @implements Command<null>
 */
final readonly class RemoveImageCommand implements Command
{
    public function __construct(public string $actorId, public ImageKind $kind, public string $ownerId, public int $expectedVersion)
    {
    }
}
