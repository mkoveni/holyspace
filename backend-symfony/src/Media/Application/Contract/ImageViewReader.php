<?php

declare(strict_types=1);

namespace App\Media\Application\Contract;

use App\Media\Domain\SlotKey;

interface ImageViewReader
{
    /** An unused slot is version 0 with no picture. */
    public function view(SlotKey $key): ImageView;

    /** Any stored picture by its id, whichever slot holds it now. */
    public function file(string $fileId): ?StoredImage;
}
