<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\GetReadiness;

use App\Shared\Application\CQRS\Query;

final readonly class GetReadinessQuery implements Query
{
    public function __construct()
    {
    }
}
