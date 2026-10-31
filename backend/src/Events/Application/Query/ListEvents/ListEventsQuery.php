<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEvents;

use App\Shared\Application\CQRS\Query;

final readonly class ListEventsQuery implements Query
{
    public function __construct()
    {
    }
}
