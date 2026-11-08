<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventCover;

final readonly class GetEventCoverQuery
{
    public function __construct(public string $eventId)
    {
    }
}
