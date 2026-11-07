<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class PolicyView
{
    public function __construct(
        public string $occurrenceId,
        public bool $open,
        public ?int $capacity,
        public bool $waitlistEnabled,
        public int $nextPosition,
        public int $version,
    ) {
    }
}
