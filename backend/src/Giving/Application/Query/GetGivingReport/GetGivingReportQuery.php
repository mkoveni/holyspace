<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingReport;

use App\Shared\Application\CQRS\Query;

final readonly class GetGivingReportQuery implements Query
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $donorId = null,
        public ?string $fundId = null,
    ) {
    }
}
