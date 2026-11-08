<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetMyGroupMeeting;

use App\Communities\Application\Contract\MemberCommunityReader;
use App\Communities\Application\Contract\MemberGroupMeeting;
use App\Communities\Application\MemberCommunityViews;
use App\Communities\Domain\CommunityException;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use Psr\Clock\ClockInterface;

/** One own meeting with the caller's own attendance only. */
final readonly class GetMyGroupMeetingHandler
{
    public function __construct(
        private MemberIdentity $identity,
        private MemberCommunityReader $reader,
        private MemberCommunityViews $views,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetMyGroupMeetingQuery $query): MemberGroupMeeting
    {
        $person = $this->identity->currentPerson() ?? throw CommunityException::missing();
        $row = $this->reader->meeting($person, $query->meetingId, $this->clock->now()) ?? throw CommunityException::missing();

        return $this->views->resolve([$row])[0];
    }
}
