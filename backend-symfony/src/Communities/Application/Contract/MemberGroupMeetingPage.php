<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class MemberGroupMeetingPage
{
    /** @param list<MemberGroupMeeting> $items */
    public function __construct(public bool $verified, public array $items, public bool $hasMore)
    {
    }
}
