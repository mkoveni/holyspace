<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class VenueView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public bool $exclusive,
        public bool $active,
        public int $version,
    ) {
    }
}
