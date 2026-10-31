<?php

declare(strict_types=1);

namespace App\Media\Application;

interface ImageTargets
{
    public function require(string $target, string $id): void;

    public function publishedEventId(string $token): string;

    public function publishedFormId(string $token): string;
}
