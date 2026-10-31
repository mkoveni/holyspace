<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

use App\Media\Application\ImageUpload;

interface ImageProcessor
{
    public function process(ImageUpload $file): array;
}
