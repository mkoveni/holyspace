<?php

declare(strict_types=1);

namespace App\Ministries\Application\Query\GetGroupWorkspace;

use App\Shared\Application\CQRS\Query;

final readonly class GetGroupWorkspaceQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
