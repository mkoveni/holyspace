<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

final readonly class FundView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public bool $active,
        public int $version,
    ) {
    }
}
