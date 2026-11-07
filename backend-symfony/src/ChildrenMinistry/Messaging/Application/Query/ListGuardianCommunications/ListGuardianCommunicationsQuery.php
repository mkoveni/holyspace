<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\ListGuardianCommunications;

final readonly class ListGuardianCommunicationsQuery
{
    public function __construct(public ?string $childId, public string $status, public int $limit, public int $offset)
    {
    }
}
