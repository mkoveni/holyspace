<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguardingFollowUp;

final readonly class GetSafeguardingFollowUpQuery
{
    public function __construct(public ?string $programmeId, public string $search, public string $kind, public string $state, public int $days, public int $limit, public int $offset)
    {
    }
}
