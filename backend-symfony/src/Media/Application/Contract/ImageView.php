<?php

declare(strict_types=1);

namespace App\Media\Application\Contract;

/** A slot as staff see it: the version to quote when changing it, and the current picture if there is one. */
final readonly class ImageView
{
    public function __construct(public int $version, public ?StoredImage $image)
    {
    }
}
