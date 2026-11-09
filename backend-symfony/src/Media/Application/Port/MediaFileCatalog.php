<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

interface MediaFileCatalog
{
    /** Whether `media_files` records a picture stored at this (main, not variant) path. */
    public function isKnown(string $path): bool;
}
