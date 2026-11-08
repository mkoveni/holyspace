<?php

declare(strict_types=1);

namespace App\Communities\Domain\Repository;

use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\RosterEntry;

/**
 * Group meetings and their rosters for commands. Lock order (Java): a person, then the Events occurrence
 * (`CommunityCalendar::lock`), then the community row, then the meeting row; the meeting row serializes its roster.
 */
interface MeetingRepository
{
    /** @phpstan-impure handlers reload it after taking their locks */
    public function findMeetingUnlocked(string $id): ?GroupMeeting;

    public function lockMeeting(string $id): ?GroupMeeting;

    public function insertMeeting(GroupMeeting $meeting): void;

    public function saveMeeting(GroupMeeting $meeting): void;

    /** @phpstan-impure handlers reload it after taking their locks */
    public function findRosterEntryUnlocked(string $id): ?RosterEntry;

    /** Adds the person unless they are already on this meeting's roster (then nothing changes). */
    public function addToRoster(RosterEntry $entry): void;

    public function saveRosterEntry(RosterEntry $entry): void;

    public function rosterSize(string $meetingId): int;
}
