<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\BuildReportSummary;

use App\Shared\Application\CQRS\Query;

final readonly class BuildReportSummaryQuery implements Query
{
    public function __construct(
        public string $from,
        public string $to,
        public array $permissions,
    ) {
    }
}
