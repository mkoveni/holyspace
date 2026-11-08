<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\ScheduleGroupMeetings;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\MeetingCalendar;
use App\Communities\Application\RequestReplay;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Schedules a reviewed series of gatherings for an active group: Events creates the meeting event (its locks come
 * first), then the community row is locked and rechecked, one meeting is linked to each date and the community version
 * moves. Managers only; a repeated request returns the first meeting.
 */
final readonly class ScheduleGroupMeetingsHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private MeetingCalendar $calendar,
        private CommunityRepository $communities,
        private MeetingRepository $meetings,
        private RequestReplay $requests,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ScheduleGroupMeetingsCommand $command): string
    {
        $this->access->manager($command->actorId);
        $hash = CommunityHash::of($command->actorId, 'SCHEDULE', $command->communityId, $command->expectedVersion, $command->schedule->javaString(), $command->reviewHash);
        $before = $this->access->community($command->communityId);
        $this->access->manager($command->actorId);
        $previous = $this->requests->previous($command->requestId, $hash, RequestReplay::ALREADY_USED);
        if (null !== $previous) {
            return $previous;
        }
        CommunityRules::version($before->version, $command->expectedVersion);
        if (!$before->active || Community::GROUP !== $before->kind) {
            throw CommunityException::conflict('Schedule meetings for an active group.');
        }
        $dates = $this->calendar->create($command->actorId, (string) $command->requestId, $before->name, $command->schedule, $command->reviewHash);

        $community = $this->communities->lockCommunity($command->communityId) ?? throw CommunityException::missing();
        $this->access->manager($command->actorId);
        $repeated = $this->requests->previous($command->requestId, $hash, RequestReplay::ALREADY_USED);
        if (null !== $repeated) {
            return $repeated;
        }
        CommunityRules::version($community->version, $command->expectedVersion);
        if (!$community->active) {
            throw CommunityException::conflict('This group is archived.');
        }
        if ([] === $dates) {
            throw CommunityException::conflict('The schedule did not produce any group meetings. Review the dates again.');
        }
        $first = null;
        foreach ($dates as $date) {
            $meeting = GroupMeeting::schedule($this->ids->uuid(), $community->id, (string) $date->id, $date->startsAt);
            $this->meetings->insertMeeting($meeting);
            $first ??= $meeting->id;
        }
        $this->communities->saveCommunity($community->touch());
        $this->requests->record((string) $command->requestId, $hash, $first);
        $this->audit->record($community->id, $first, 'MEETINGS_SCHEDULED', 'Scheduled '.\count($dates).' meetings.', $command->actorId, $this->clock->now());

        return $first;
    }
}
