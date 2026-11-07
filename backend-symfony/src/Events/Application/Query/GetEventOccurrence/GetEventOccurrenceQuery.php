<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventOccurrence;

final readonly class GetEventOccurrenceQuery
{
    public function __construct(public string $occurrenceId)
    {
    }
}
