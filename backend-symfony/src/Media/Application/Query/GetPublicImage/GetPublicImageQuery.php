<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPublicImage;

final readonly class GetPublicImageQuery
{
    /** @param int|null $variant the width of a smaller copy; null for the stored picture */
    public function __construct(public string $fileId, public ?int $variant = null)
    {
    }
}
