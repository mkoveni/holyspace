<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ExportGivingCsv;

use App\Shared\Application\CQRS\Query;

final readonly class ExportGivingCsvQuery implements Query
{
    public function __construct(public array $report)
    {
    }
}
