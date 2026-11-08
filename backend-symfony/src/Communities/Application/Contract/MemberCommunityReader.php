<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/**
 * Java `MemberCommunityReader`. Every read is bounded by the person's current membership (half-open) of an active
 * community; meetings scheduled before that membership began stay hidden unless the person has their own roster row.
 */
interface MemberCommunityReader
{
    /** @return list<MemberCommunity> by name, then id */
    public function communities(string $personId, ?string $kind, DateTimeImmutable $at, int $limit, int $offset): array;

    public function community(string $personId, string $communityId, DateTimeImmutable $at): ?MemberCommunity;

    /** @return list<LeaderEntry> current leaders who are current members, by role, then appointment id */
    public function leaders(string $personId, string $communityId, DateTimeImmutable $at, int $limit, int $offset): array;

    /** @return list<MemberMeetingRow> scheduled in [from, until), by time, then id */
    public function meetings(string $personId, ?string $communityId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array;

    public function meeting(string $personId, string $meetingId, DateTimeImmutable $at): ?MemberMeetingRow;
}
