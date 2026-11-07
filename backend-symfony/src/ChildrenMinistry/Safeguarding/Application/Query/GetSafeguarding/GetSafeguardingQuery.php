<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguarding;

final readonly class GetSafeguardingQuery
{
    public function __construct(public string $childId)
    {
    }
}
