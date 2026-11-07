<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetMyEvent;

final readonly class GetMyEventQuery
{
    public function __construct(public string $occurrenceId)
    {
    }
}
