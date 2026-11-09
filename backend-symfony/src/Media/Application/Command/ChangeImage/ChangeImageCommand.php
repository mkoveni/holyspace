<?php

declare(strict_types=1);

namespace App\Media\Application\Command\ChangeImage;

use App\Media\Domain\ImageKind;
use App\Shared\Application\Command;

/**
 * Sets or replaces one owner's picture from an uploaded file's bytes.
 *
 * @implements Command<null>
 */
final readonly class ChangeImageCommand implements Command
{
    public function __construct(public string $actorId, public ImageKind $kind, public string $ownerId, public string $bytes, public int $expectedVersion)
    {
    }

    /** @return array<string, mixed> never the bytes */
    public function __debugInfo(): array
    {
        return ['kind' => $this->kind, 'ownerId' => $this->ownerId, 'bytes' => \strlen($this->bytes)];
    }
}
