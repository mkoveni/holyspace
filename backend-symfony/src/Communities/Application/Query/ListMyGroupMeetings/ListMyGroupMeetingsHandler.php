<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListMyGroupMeetings;

use App\Communities\Application\Contract\MemberCommunityReader;
use App\Communities\Application\Contract\MemberGroupMeetingPage;
use App\Communities\Application\MemberCommunityViews;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use Psr\Clock\ClockInterface;

/** Meetings of the caller's current communities in the next 180 days; reading never prepares a roster. */
final readonly class ListMyGroupMeetingsHandler
{
    private const int WINDOW_DAYS = 180;

    public function __construct(
        private MemberIdentity $identity,
        private MemberCommunityReader $reader,
        private MemberCommunityViews $views,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ListMyGroupMeetingsQuery $query): MemberGroupMeetingPage
    {
        CommunityRules::page($query->limit, $query->offset);
        $person = $this->identity->currentPerson();
        if (null === $person) {
            return new MemberGroupMeetingPage(false, [], false);
        }
        $now = $this->clock->now();
        if (null !== $query->communityId && null === $this->reader->community($person, $query->communityId, $now)) {
            throw CommunityException::missing();
        }
        $rows = $this->reader->meetings($person, $query->communityId, $now, $now->modify('+'.self::WINDOW_DAYS.' days'), $query->limit + 1, $query->offset);

        return new MemberGroupMeetingPage(true, $this->views->resolve(\array_slice($rows, 0, $query->limit)), \count($rows) > $query->limit);
    }
}
