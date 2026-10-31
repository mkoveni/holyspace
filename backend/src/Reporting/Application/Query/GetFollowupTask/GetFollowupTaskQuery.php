<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\GetFollowupTask;

use App\Shared\Application\CQRS\Query;

final readonly class GetFollowupTaskQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
