<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingTotals;

use App\Shared\Application\CQRS\Query;

final readonly class GetGivingTotalsQuery implements Query
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $fundId = null,
    ) {
    }
}
