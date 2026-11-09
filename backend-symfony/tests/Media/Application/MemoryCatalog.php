<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Port\MediaFileCatalog;

final class MemoryCatalog implements MediaFileCatalog
{
    /** @param list<string> $known */
    public function __construct(private readonly array $known = [])
    {
    }

    public function isKnown(string $path): bool
    {
        return in_array($path, $this->known, true);
    }
}
