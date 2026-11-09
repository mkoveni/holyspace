<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

/** The database-stored event and community covers that predate Media. Goes away with the legacy tables. */
interface LegacyCoverSource
{
    /** @return list<LegacyCover> every cover that still has bytes, in a stable order */
    public function covers(): array;

    /** The original uploaded bytes, or null when the legacy row is gone. */
    public function bytes(string $imageId): ?string;
}
