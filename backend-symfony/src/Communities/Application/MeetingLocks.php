<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communities\Domain\CommunityException;
use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Events\Application\Contract\CalendarOccurrence;
use DateTimeImmutable;

/**
 * Java `GroupMeetings.before/lockSource/lock/open`. Takes the meeting's locks in the documented order (the Events
 * occurrence, then the community, then the meeting) and rechecks the caller's scope once the community is locked.
 */
final readonly class MeetingLocks
{
    /** Rosters are prepared and first attendance recorded from this long before the start. */
    private const string PREPARATION_WINDOW = '-7200 seconds';

    public function __construct(
        private MeetingRepository $meetings,
        private CommunityRepository $communities,
        private MeetingCalendar $calendar,
        private CommunityAccess $access,
    ) {
    }

    public function lock(string $meetingId, string $actorId): LockedMeeting
    {
        $before = $this->meetings->findMeetingUnlocked($meetingId) ?? throw CommunityException::missing();
        $date = $this->calendar->lock($before->occurrenceId);
        $this->communities->lockCommunity($before->communityId) ?? throw CommunityException::missing();
        $this->access->actor($actorId);
        $this->access->require($before->communityId);

        return new LockedMeeting($this->meetings->lockMeeting($meetingId) ?? throw CommunityException::missing(), $date);
    }

    /** New attendance needs an open meeting, a still scheduled occurrence and an active community. */
    public function requireOpen(GroupMeeting $meeting, CalendarOccurrence $date): void
    {
        if (!$meeting->isOpen() || 'SCHEDULED' !== $date->status || !$this->access->community($meeting->communityId)->active) {
            throw CommunityException::conflict('This meeting is closed to new attendance.');
        }
    }

    public static function beforeWindow(CalendarOccurrence $date, DateTimeImmutable $now): bool
    {
        return $now < $date->startsAt->modify(self::PREPARATION_WINDOW);
    }
}
