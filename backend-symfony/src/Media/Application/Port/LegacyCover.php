<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

use App\Media\Domain\ImageKind;

/** One picture of the pre-Media cover tables: which owner it belongs to and the id its old public URL carries. */
final readonly class LegacyCover
{
    public function __construct(public ImageKind $kind, public string $ownerId, public string $imageId)
    {
    }
}
