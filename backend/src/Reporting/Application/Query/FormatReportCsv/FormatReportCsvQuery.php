<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\FormatReportCsv;

use App\Shared\Application\CQRS\Query;

final readonly class FormatReportCsvQuery implements Query
{
    public function __construct(public array $rows)
    {
    }
}
