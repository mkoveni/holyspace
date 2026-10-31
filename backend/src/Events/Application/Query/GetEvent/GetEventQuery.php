<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEvent;

use App\Shared\Application\CQRS\Query;

final readonly class GetEventQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
