<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Media\Application\Contract\ImageOwnership;
use App\Media\Domain\MediaException;
use App\Media\Domain\OwnerType;

/** The owning contexts' implementations, collected by the `media.image_owners` tag, looked up by owner type. */
final readonly class ImageOwners
{
    /** @var array<string, ImageOwnership> */
    private array $byType;

    /** @param iterable<ImageOwnership> $owners */
    public function __construct(iterable $owners)
    {
        $byType = [];
        foreach ($owners as $owner) {
            $byType[$owner->ownerType()] = $owner;
        }
        $this->byType = $byType;
    }

    /** An owner type no context has registered yet has no pictures, so it is a not-found rather than a server error. */
    public function for(OwnerType $type): ImageOwnership
    {
        return $this->byType[$type->value] ?? throw MediaException::missing();
    }
}
