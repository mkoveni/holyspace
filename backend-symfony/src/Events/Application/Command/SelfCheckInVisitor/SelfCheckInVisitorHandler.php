<?php

declare(strict_types=1);

namespace App\Events\Application\Command\SelfCheckInVisitor;

use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Application\Contract\VisitTokens;
use App\Events\Application\EventAttendance;
use App\Events\Application\VisitorMessenger;
use App\Events\Application\VisitorWelcome;
use App\Events\Domain\AdmissionWindow;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\Events\Domain\Repository\VisitLinkRepository;
use App\Events\Domain\Repository\VisitorCheckInRepository;
use App\Events\Domain\Repository\VisitorChildRepository;
use App\Events\Domain\VisitorCheckIn;
use App\Events\Domain\VisitorChild;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\VisitorDetails;
use App\People\Application\Contract\VisitorRegistrar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * A first-time visitor checks in from the occurrence's QR link. The token is the only credential, so every answer
 * to a bad or switched-off link is the same "not found". The visitor is the actor of the records this writes.
 * Lock order is the person, then the occurrence, as for every other booking.
 */
final readonly class SelfCheckInVisitorHandler implements CommandHandler
{
    public function __construct(
        private VisitTokens $tokens,
        private VisitLinkRepository $links,
        private OccurrenceRepository $occurrences,
        private EventReader $events,
        private VisitorRegistrar $registrar,
        private PersonDirectory $people,
        private RegistrationRepository $registrations,
        private AttendanceReader $attendanceReader,
        private EventAttendance $attendance,
        private VisitorCheckInRepository $visits,
        private VisitorChildRepository $children,
        private VisitorMessenger $messenger,
        private EventJournal $journal,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SelfCheckInVisitorCommand $command): VisitorWelcome
    {
        if (!$command->consent) {
            throw EventException::invalid('Please agree to the church contacting you and keeping your details.');
        }
        EventRules::text($command->consentVersion, 40);
        if (\count($command->children) > VisitorChild::MAX_PER_VISIT) {
            throw EventException::invalid('You can add up to '.VisitorChild::MAX_PER_VISIT.' children.');
        }
        $claim = $this->tokens->verify($command->token) ?? throw EventException::missing('Check-in link');
        $link = $this->links->findUnlocked($claim->occurrenceId);
        if (null === $link || !$link->accepts($claim->generation)) {
            throw EventException::missing('Check-in link');
        }
        $occurrence = $this->occurrences->findUnlocked($claim->occurrenceId) ?? throw EventException::missing('Check-in link');
        $event = $this->events->find($occurrence->eventId);
        if (null === $event || !$event->active) {
            throw EventException::missing('Check-in link');
        }
        AdmissionWindow::require($occurrence, $this->clock->now());
        if (!($this->registrations->findPolicy($occurrence->id)->open ?? false)) {
            throw EventException::conflict('Check-in is not available right now. Please see the welcome team.');
        }

        $visitor = $this->registrar->registerOrFind(new VisitorDetails($command->firstName, $command->lastName, $command->mobile, $command->email));
        $personId = $visitor->personId;
        $this->people->lock($personId) ?? throw EventException::missing('Available person');
        $this->occurrences->findForUpdate($occurrence->id) ?? throw EventException::missing('Check-in link');

        $existing = $this->registrations->findRegistrationOfPerson($occurrence->id, $personId);
        if (null !== $existing && 'WAITLISTED' === $existing->status) {
            return new VisitorWelcome($event->title, false);
        }
        if (null !== $existing && $this->attendanceReader->isPresent($existing->id)) {
            return new VisitorWelcome($event->title, true);
        }
        $registrationId = $this->arrive($personId, $occurrence->id, $existing);
        $admitted = $this->attendanceReader->isPresent($registrationId);

        $now = $this->clock->now();
        if (!$this->visits->exists($registrationId)) {
            $children = [];
            foreach (array_values($command->children) as $position => $child) {
                $children[] = VisitorChild::arrive($this->ids->uuid(), $registrationId, $child['firstName'], $position, $child['birthDate'], $now);
            }
            $this->visits->insert(VisitorCheckIn::arrive(
                $registrationId,
                $occurrence->id,
                trim($command->firstName).' '.trim($command->lastName),
                $command->mobile,
                $command->email,
                $command->howHeard,
                $command->partyAdults,
                $visitor->firstTime,
                $command->consentVersion,
                $now,
            ));
            foreach ($children as $child) {
                $this->children->insert($child);
            }
            $this->messenger->onArrival($registrationId, OccurrenceView::of($occurrence), $event->title, trim($command->firstName), $command->email, $now);
        }
        $this->journal->audit($personId, 'visitor.self_checked_in', $registrationId, 'Visitor checked in through the occurrence QR link.', $now);

        return new VisitorWelcome($event->title, $admitted);
    }

    /** Books the visitor at the door, or checks in a booking they already had. */
    private function arrive(string $personId, string $occurrenceId, ?EventRegistration $existing): string
    {
        $requestId = $this->ids->uuid();
        if (null !== $existing && 'CONFIRMED' === $existing->status) {
            $this->attendance->checkIn($personId, $requestId, $existing->id, $existing->revision->version);

            return $existing->id;
        }
        $version = null === $existing ? null : $existing->revision->version;

        return $this->attendance->walkIn($personId, $requestId, $occurrenceId, $personId, $version)->registrationId;
    }
}
