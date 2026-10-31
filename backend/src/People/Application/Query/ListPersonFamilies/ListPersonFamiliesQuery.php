<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListPersonFamilies;

use App\Shared\Application\CQRS\Query;

final readonly class ListPersonFamiliesQuery implements Query
{
    public function __construct(public string $personId)
    {
    }
}
