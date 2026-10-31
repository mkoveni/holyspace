<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\GetReportSummary;

use App\Shared\Application\CQRS\Query;

final readonly class GetReportSummaryQuery implements Query
{
    public function __construct(public string $from, public string $to)
    {
    }
}
