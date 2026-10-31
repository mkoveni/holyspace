<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Query\GetOwnPublishedPlans;

use App\Shared\Application\CQRS\Query;

final readonly class GetOwnPublishedPlansQuery implements Query
{
    public function __construct()
    {
    }
}
