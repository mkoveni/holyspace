<?php

declare(strict_types=1);

namespace App\Shared\Application\CoverImage;

use App\Shared\Domain\CoverImage;
use DateTimeImmutable;

/**
 * Bytes of event and community cover images. Each upload gets a new random id, so a stored image never changes and its
 * public URL can be cached forever; the owning context keeps the pointer and removes the image it replaced.
 */
interface CoverImageStore
{
    public function add(string $id, CoverImage $image, DateTimeImmutable $at): void;

    public function remove(string $id): void;

    public function info(string $id): ?CoverImageInfo;

    public function content(string $id): ?CoverImageContent;
}
