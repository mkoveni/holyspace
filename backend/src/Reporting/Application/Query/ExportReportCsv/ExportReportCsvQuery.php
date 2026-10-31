<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\ExportReportCsv;

use App\Shared\Application\CQRS\Query;

final readonly class ExportReportCsvQuery implements Query
{
    public function __construct(public array $rows)
    {
    }
}
