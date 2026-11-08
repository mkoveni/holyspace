<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\AddGroupGuest;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\MeetingLocks;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Communities\Domain\RosterEntry;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Adds a guest to a prepared, open meeting. A guest does not join the group, and someone already listed stays listed
 * once. Lock order: the person, then the meeting's locks ({@see MeetingLocks}).
 */
final readonly class AddGroupGuestHandler implements CommandHandler
{
    private const int MAX_ROSTER = 5000;

    public function __construct(
        private CommunityAccess $access,
        private PersonDirectory $people,
        private MeetingLocks $locks,
        private MeetingRepository $meetings,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AddGroupGuestCommand $command): void
    {
        $this->access->actor($command->actorId);
        $note = CommunityRules::text($command->reason, 2000);
        $person = $this->people->lock($command->personId);
        if (null === $person || !$person->available) {
            throw CommunityException::missing();
        }
        $locked = $this->locks->lock($command->meetingId, $command->actorId);
        $meeting = $locked->meeting;
        CommunityRules::version($meeting->version, $command->expectedVersion);
        $this->locks->requireOpen($meeting, $locked->date);
        $next = $meeting->guestAdded();
        if ($this->meetings->rosterSize($meeting->id) >= self::MAX_ROSTER) {
            throw CommunityException::conflict('This meeting roster is full.');
        }
        $this->meetings->addToRoster(RosterEntry::guest($this->ids->uuid(), $meeting->id, $command->personId));
        $this->meetings->saveMeeting($next);
        $this->audit->record($meeting->communityId, $meeting->id, 'GUEST_ADDED', $note, $command->actorId, $this->clock->now());
    }
}
