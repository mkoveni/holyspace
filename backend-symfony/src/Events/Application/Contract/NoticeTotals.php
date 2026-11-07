<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class NoticeTotals
{
    public function __construct(
        public int $approved,
        public int $submitted,
        public int $delivered,
        public int $pending,
        public int $failed,
        public int $captured,
        public int $cancelled,
        public int $remindersApproved,
        public bool $complete,
    ) {
    }

    public function unresolved(): int
    {
        return $this->pending + $this->failed + $this->captured;
    }
}
