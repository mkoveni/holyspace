<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class ReportPage
{
    /** @param list<ReportRow> $occurrences */
    public function __construct(public array $occurrences, public ReportTotals $totals)
    {
    }
}
