<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

/** A file waiting to be removed from disk: the stored path of the picture whose smaller copies go with it. */
final readonly class PendingFile
{
    public function __construct(public string $fileId, public string $path)
    {
    }
}
