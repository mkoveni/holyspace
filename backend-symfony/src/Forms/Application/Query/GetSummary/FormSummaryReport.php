<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetSummary;

final readonly class FormSummaryReport
{
    /** @param list<array<string, mixed>> $questions */
    public function __construct(public int $responses, public bool $hidden, public int $minimumForAnonymous, public array $questions)
    {
    }
}
