<?php

declare(strict_types=1);

namespace App\Media\Application\Contract;

use App\Media\Domain\ImageKind;
use DateTimeImmutable;

/** What is known of one stored picture. The path is for the storage adapter and must never reach an API response. */
final readonly class StoredImage
{
    public function __construct(
        public string $fileId,
        public ImageKind $kind,
        public string $path,
        public string $mime,
        public int $width,
        public int $height,
        public int $byteSize,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
