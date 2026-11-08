<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/** An owner's cover pointer: version 0 means it was never set; a null image id means none is set now. */
final readonly class CoverSlot
{
    public function __construct(public ?string $imageId, public int $version)
    {
    }

    public static function empty(): self
    {
        return new self(null, 0);
    }
}
