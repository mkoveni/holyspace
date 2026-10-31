<?php

declare(strict_types=1);

namespace App\Media\Application\Command\ChangePortalImage;

use App\Media\Application\ImageUpload;
use App\Shared\Application\CQRS\Command;

final readonly class ChangePortalImageCommand implements Command
{
    public function __construct(
        public string $target,
        public string $id,
        public ?ImageUpload $file,
        public int $expectedVersion,
    ) {
    }
}
