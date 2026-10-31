<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Query\ListServicePlans;

use App\Shared\Application\CQRS\Query;

final readonly class ListServicePlansQuery implements Query
{
    public function __construct()
    {
    }
}
