<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\FinishGroupMeeting;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\MeetingLocks;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Cancels an open meeting (managers only) or closes it once it has ended with a prepared roster. Roster rows, unrecorded
 * ones included, are kept as evidence.
 */
final readonly class FinishGroupMeetingHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private MeetingLocks $locks,
        private MeetingRepository $meetings,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(FinishGroupMeetingCommand $command): void
    {
        $this->access->actor($command->actorId);
        $note = CommunityRules::text($command->reason, 2000);
        $status = $command->status;
        if (GroupMeeting::CANCELLED !== $status && GroupMeeting::CLOSED !== $status) {
            throw CommunityException::invalid('Choose cancel or close.');
        }
        $locked = $this->locks->lock($command->meetingId, $command->actorId);
        $meeting = $locked->meeting;
        CommunityRules::version($meeting->version, $command->expectedVersion);
        $meeting->requireOpen();
        if (GroupMeeting::CANCELLED === $status) {
            $this->access->manager($command->actorId);
        }
        $now = $this->clock->now();
        $this->meetings->saveMeeting($meeting->finish($status, $now >= $locked->date->endsAt));
        $this->audit->record($meeting->communityId, $meeting->id, 'MEETING_'.$status, $note, $command->actorId, $now);
    }
}
