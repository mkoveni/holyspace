<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\EvaluateReportCriteria;

use App\Shared\Application\CQRS\Query;

final readonly class EvaluateReportCriteriaQuery implements Query
{
    public function __construct(public array $filters)
    {
    }
}
