<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetMyGroupMeeting;

final readonly class GetMyGroupMeetingQuery
{
    public function __construct(public string $meetingId)
    {
    }
}
