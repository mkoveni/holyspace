<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetChurchEvent;

final readonly class GetChurchEventQuery
{
    public function __construct(public string $eventId, public int $limit, public int $offset)
    {
    }
}
