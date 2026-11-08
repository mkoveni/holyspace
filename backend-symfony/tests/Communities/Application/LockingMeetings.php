<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Communities\Domain\RosterEntry;
use App\Tests\Events\Application\LockLog;

/** In-memory meetings and roster rows that record when a meeting row is locked. */
final class LockingMeetings implements MeetingRepository
{
    /** @var array<string, GroupMeeting> */
    public array $meetings = [];

    /** @var array<string, RosterEntry> */
    public array $roster = [];

    /** Loads taken without a lock; handlers reload after locking, so this is counted rather than cached. */
    public int $unlockedReads = 0;

    public function __construct(private readonly LockLog $log)
    {
    }

    public function findMeetingUnlocked(string $id): ?GroupMeeting
    {
        ++$this->unlockedReads;

        return $this->meetings[$id] ?? null;
    }

    public function lockMeeting(string $id): ?GroupMeeting
    {
        $this->log->locks[] = 'meeting:'.$id;

        return $this->meetings[$id] ?? null;
    }

    public function insertMeeting(GroupMeeting $meeting): void
    {
        $this->meetings[$meeting->id] = $meeting;
    }

    public function saveMeeting(GroupMeeting $meeting): void
    {
        $this->meetings[$meeting->id] = $meeting;
    }

    public function findRosterEntryUnlocked(string $id): ?RosterEntry
    {
        ++$this->unlockedReads;

        return $this->roster[$id] ?? null;
    }

    public function addToRoster(RosterEntry $entry): void
    {
        foreach ($this->roster as $existing) {
            if ($existing->meetingId === $entry->meetingId && $existing->personId === $entry->personId) {
                return;
            }
        }
        $this->roster[$entry->id] = $entry;
    }

    public function saveRosterEntry(RosterEntry $entry): void
    {
        $this->roster[$entry->id] = $entry;
    }

    public function rosterSize(string $meetingId): int
    {
        return \count(array_filter($this->roster, static fn (RosterEntry $e): bool => $e->meetingId === $meetingId));
    }
}
