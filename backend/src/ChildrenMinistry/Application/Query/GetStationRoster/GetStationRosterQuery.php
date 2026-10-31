<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetStationRoster;

use App\Shared\Application\CQRS\Query;

final readonly class GetStationRosterQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
