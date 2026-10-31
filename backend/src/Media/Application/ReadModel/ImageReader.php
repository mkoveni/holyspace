<?php

declare(strict_types=1);

namespace App\Media\Application\ReadModel;

interface ImageReader
{
    public function get(string $target, string $id, bool $content = false): array;
}
