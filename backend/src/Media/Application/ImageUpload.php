<?php

declare(strict_types=1);

namespace App\Media\Application;

final readonly class ImageUpload
{
    public function __construct(public string $path, public int $size, public bool $valid)
    {
    }
}
