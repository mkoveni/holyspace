<?php

declare(strict_types=1);

namespace App\Shared\Application\CoverImage;

use DateTimeImmutable;

/** What staff see of a stored cover image: no bytes. */
final readonly class CoverImageInfo
{
    public function __construct(
        public string $id,
        public string $contentType,
        public int $width,
        public int $height,
        public int $byteSize,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
