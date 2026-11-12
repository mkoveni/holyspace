<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ReviewVisitor;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\EventAttendance;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\VisitorCheckInRepository;
use App\Events\Domain\VisitorCheckIn;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Staff confirm a visitor's check-in, or remove it, which voids the attendance through the correction flow. */
final readonly class ReviewVisitorHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VisitorCheckInRepository $visits,
        private EventAttendance $attendance,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ReviewVisitorCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');
        $reason = EventRules::text($command->reason, 1000);
        $registrationId = EventRules::id($command->registrationId);
        $status = $this->visits->reviewStatus($registrationId) ?? throw EventException::missing('Visitor check-in');
        $now = $this->clock->now();

        if (ReviewVisitorCommand::CONFIRM === $command->decision) {
            if (VisitorCheckIn::REMOVED === $status) {
                throw EventException::conflict('This visitor check-in was removed.');
            }
            $this->visits->review($registrationId, VisitorCheckIn::CONFIRMED, $command->actorId, $now);
            $this->journal->audit($command->actorId, 'visitor.confirmed', $registrationId, $reason, $now);

            return;
        }
        if (ReviewVisitorCommand::REMOVE !== $command->decision) {
            throw EventException::invalid('Choose confirm or remove.');
        }
        if (VisitorCheckIn::REMOVED === $status) {
            return;
        }
        if (null !== $command->expectedAttendanceVersion) {
            $this->attendance->correct($command->actorId, $registrationId, $command->expectedAttendanceVersion, 'VOIDED', $reason);
        }
        $this->visits->review($registrationId, VisitorCheckIn::REMOVED, $command->actorId, $now);
        $this->journal->audit($command->actorId, 'visitor.removed', $registrationId, $reason, $now);
    }
}
