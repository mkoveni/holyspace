<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPerson;

use App\Shared\Application\CQRS\Query;

final readonly class GetPersonQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
