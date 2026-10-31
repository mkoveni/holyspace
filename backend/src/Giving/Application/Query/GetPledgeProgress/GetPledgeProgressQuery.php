<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetPledgeProgress;

use App\Shared\Application\CQRS\Query;

final readonly class GetPledgeProgressQuery implements Query
{
    public function __construct(public string $asOf)
    {
    }
}
