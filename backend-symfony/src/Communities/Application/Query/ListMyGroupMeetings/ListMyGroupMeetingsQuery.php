<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListMyGroupMeetings;

final readonly class ListMyGroupMeetingsQuery
{
    public function __construct(public ?string $communityId, public int $limit, public int $offset)
    {
    }
}
