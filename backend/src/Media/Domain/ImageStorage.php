<?php

declare(strict_types=1);

namespace App\Media\Domain;

interface ImageStorage
{
    public function get(string $target, string $id, bool $content = false): array;

    public function save(string $target, string $id, ?array $image, int $expectedVersion): array;
}
