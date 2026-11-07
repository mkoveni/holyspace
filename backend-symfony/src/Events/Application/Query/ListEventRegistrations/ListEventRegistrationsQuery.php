<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventRegistrations;

final readonly class ListEventRegistrationsQuery
{
    public function __construct(public string $occurrenceId, public ?string $status, public int $limit, public int $offset)
    {
    }
}
