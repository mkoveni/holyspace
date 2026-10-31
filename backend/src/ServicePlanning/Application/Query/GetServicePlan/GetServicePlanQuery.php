<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Query\GetServicePlan;

use App\Shared\Application\CQRS\Query;

final readonly class GetServicePlanQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
