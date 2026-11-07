<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class EventView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $type,
        public string $description,
        public bool $active,
        public int $version,
    ) {
    }
}
