<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPortalImage;

use App\Shared\Application\CQRS\Query;

final readonly class GetPortalImageQuery implements Query
{
    public function __construct(
        public string $target,
        public string $id,
        public bool $content = false,
    ) {
    }
}
