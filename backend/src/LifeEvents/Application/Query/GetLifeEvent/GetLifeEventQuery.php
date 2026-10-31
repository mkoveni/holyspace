<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Query\GetLifeEvent;

use App\Shared\Application\CQRS\Query;

final readonly class GetLifeEventQuery implements Query
{
    public function __construct(public string $lifeEventId)
    {
    }
}
