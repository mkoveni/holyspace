<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConvertVisitor;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\VisitorReader;
use App\Events\Application\VisitorMessageRules;
use App\Events\Application\VisitorMessenger;
use App\Events\Domain\EventException;
use App\Events\Domain\VisitorCheckIn;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\MembershipConverter;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Staff make a visitor a member and, if asked, invite them to the church app by email. Becoming a member needs
 * people.manage; sending the invitation needs events.communications.send and an email address the visitor gave.
 * Repeating the request is harmless: membership is not re-applied and an invitation is only ever queued once.
 */
final readonly class ConvertVisitorHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VisitorReader $visitors,
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private MembershipConverter $members,
        private VisitorMessenger $messenger,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ConvertVisitorCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');
        if ($command->inviteToApp) {
            $this->authorization->requireActorPermission($command->actorId, VisitorMessageRules::PERMISSION);
        }
        $visit = $this->visitors->find($command->registrationId) ?? throw EventException::missing('Visitor check-in');
        if (VisitorCheckIn::REMOVED === $visit->reviewStatus) {
            throw EventException::conflict('This visitor check-in was removed.');
        }
        $personId = $this->visitors->personId($visit->registrationId) ?? throw EventException::missing('Visitor check-in');
        $now = $this->clock->now();

        $email = $visit->email;
        if ($command->inviteToApp && null === $email) {
            throw EventException::invalid('This visitor gave no email address to send an invitation to.');
        }
        if ($this->members->admit($command->actorId, $personId)) {
            $this->journal->audit($command->actorId, 'visitor.converted', $visit->registrationId, 'Visitor made a member.', $now);
        }
        if ($command->inviteToApp) {
            $occurrence = $this->occurrences->find($visit->occurrenceId) ?? throw EventException::missing('Occurrence');
            $event = $this->events->find($occurrence->eventId) ?? throw EventException::missing('Event');
            $this->messenger->inviteToApp($visit->registrationId, $command->actorId, $event->title, trim(explode(' ', $visit->fullName)[0]), $email, $now);
        }
    }
}
