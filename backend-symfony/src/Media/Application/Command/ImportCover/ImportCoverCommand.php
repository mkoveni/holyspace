<?php

declare(strict_types=1);

namespace App\Media\Application\Command\ImportCover;

use App\Media\Domain\ImageKind;
use App\Shared\Application\Command;

/**
 * Moves one legacy cover into Media. Returns `imported` or `skipped` (Media already used the slot).
 *
 * @implements Command<string>
 */
final readonly class ImportCoverCommand implements Command
{
    public function __construct(public ImageKind $kind, public string $ownerId, public string $imageId)
    {
    }
}
