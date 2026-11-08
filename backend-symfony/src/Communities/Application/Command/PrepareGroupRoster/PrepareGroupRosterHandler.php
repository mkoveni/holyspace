<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\PrepareGroupRoster;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\MeetingLocks;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Communities\Domain\RosterEntry;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Freezes the member roster from two hours before an open meeting: everyone who is a member at the start time is
 * listed once (at most 5,000). Preparing again keeps the roster and only records the preparation.
 */
final readonly class PrepareGroupRosterHandler implements CommandHandler
{
    private const int MAX_ROSTER = 5000;

    public function __construct(
        private CommunityAccess $access,
        private MeetingLocks $locks,
        private CommunityRepository $communities,
        private MeetingRepository $meetings,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(PrepareGroupRosterCommand $command): void
    {
        $this->access->actor($command->actorId);
        $locked = $this->locks->lock($command->meetingId, $command->actorId);
        $meeting = $locked->meeting;
        CommunityRules::version($meeting->version, $command->expectedVersion);
        $this->locks->requireOpen($meeting, $locked->date);
        $now = $this->clock->now();
        if (MeetingLocks::beforeWindow($locked->date, $now)) {
            throw CommunityException::conflict('Prepare the meeting roster from two hours before it starts.');
        }
        if (!$meeting->rosterFrozen) {
            $members = $this->communities->membershipsAt($meeting->communityId, $locked->date->startsAt, self::MAX_ROSTER + 1);
            if (\count($members) > self::MAX_ROSTER) {
                throw CommunityException::conflict('A meeting roster can contain at most 5,000 members.');
            }
            foreach ($members as $membership) {
                $this->meetings->addToRoster(RosterEntry::member($this->ids->uuid(), $meeting->id, $membership));
            }
            $this->meetings->saveMeeting($meeting->freeze());
        }
        $this->audit->record($meeting->communityId, $meeting->id, 'ROSTER_PREPARED', 'Membership roster frozen for this gathering.', $command->actorId, $now);
    }
}
