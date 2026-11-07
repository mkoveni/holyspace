<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListMyEvents;

final readonly class ListMyEventsQuery
{
    public function __construct(public bool $mine, public int $limit, public int $offset)
    {
    }
}
