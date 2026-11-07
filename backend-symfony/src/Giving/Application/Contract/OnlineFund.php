<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** A fund with its online-giving flag (Java `OnlineFundDirectory.Entry`). */
final readonly class OnlineFund
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public bool $active,
        public bool $enabled,
        public int $version,
    ) {
    }
}
