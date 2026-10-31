<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Query\GetPersonLifeEvents;

use App\Shared\Application\CQRS\Query;

final readonly class GetPersonLifeEventsQuery implements Query
{
    public function __construct(public string $personId)
    {
    }
}
