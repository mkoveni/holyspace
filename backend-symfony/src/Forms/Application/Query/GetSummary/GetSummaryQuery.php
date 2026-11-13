<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetSummary;

final readonly class GetSummaryQuery
{
    public function __construct(public string $formId)
    {
    }
}
