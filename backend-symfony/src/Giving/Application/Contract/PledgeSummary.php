<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

final readonly class PledgeSummary
{
    public function __construct(
        public PledgeView $pledge,
        public string $donorName,
        public string $fundName,
        public int $creditedMinor,
        public int $remainingMinor,
        public bool $fulfilled,
    ) {
    }
}
