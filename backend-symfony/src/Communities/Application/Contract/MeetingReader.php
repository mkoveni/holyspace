<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** Read models for group meetings and their rosters. */
interface MeetingReader
{
    public function meeting(string $id): ?MeetingView;

    /** @return list<MeetingView> newest scheduled first, then id */
    public function meetings(string $communityId, int $limit, int $offset): array;

    public function meetingCount(string $communityId): int;

    /** @return list<RosterEntryView> by id */
    public function roster(string $meetingId, int $limit, int $offset): array;

    public function rosterCount(string $meetingId): int;

    /**
     * @param list<string> $meetingIds
     *
     * @return array<string, AttendanceReport> keyed by meeting id; every existing meeting has one
     */
    public function reports(array $meetingIds): array;
}
