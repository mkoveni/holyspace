<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\RecordGroupAttendance;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\MeetingLocks;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Communities\Domain\RosterEntry;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Records or corrects one roster row's attendance with a reason. The first recording needs an open meeting and starts
 * two hours before it; later corrections are allowed even after the meeting is closed or cancelled. An unchanged status
 * writes nothing. Lock order: the person, then the meeting's locks ({@see MeetingLocks}).
 */
final readonly class RecordGroupAttendanceHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private PersonDirectory $people,
        private MeetingLocks $locks,
        private MeetingRepository $meetings,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RecordGroupAttendanceCommand $command): void
    {
        $this->access->actor($command->actorId);
        $note = CommunityRules::text($command->reason, 2000);
        $status = RosterEntry::requireStatus($command->status);
        $before = $this->meetings->findRosterEntryUnlocked($command->rosterId) ?? throw CommunityException::missing();
        $this->people->lock($before->personId) ?? throw CommunityException::missing();
        $locked = $this->locks->lock($before->meetingId, $command->actorId);
        $entry = $this->meetings->findRosterEntryUnlocked($command->rosterId) ?? throw CommunityException::missing();
        CommunityRules::version($entry->version, $command->expectedVersion);
        $now = $this->clock->now();
        if ($entry->isFirstRecording()) {
            $this->locks->requireOpen($locked->meeting, $locked->date);
            if (MeetingLocks::beforeWindow($locked->date, $now)) {
                throw CommunityException::conflict('Record attendance from two hours before the meeting.');
            }
        }
        if ($entry->status !== $status) {
            $this->meetings->saveRosterEntry($entry->record($status));
            $this->audit->record($locked->meeting->communityId, $entry->id, 'ATTENDANCE_'.$status, $note, $command->actorId, $now);
        }
    }
}
