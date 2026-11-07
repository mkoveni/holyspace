<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventReadiness;

final readonly class GetEventReadinessQuery
{
    public function __construct(public string $occurrenceId)
    {
    }
}
