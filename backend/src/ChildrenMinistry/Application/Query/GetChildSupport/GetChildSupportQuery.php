<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetChildSupport;

use App\Shared\Application\CQRS\Query;

final readonly class GetChildSupportQuery implements Query
{
    public function __construct(public string $sessionId, public string $childId)
    {
    }
}
