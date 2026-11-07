<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\MemberBookingUpdates;
use App\Events\Application\Contract\MemberEventReader;
use App\Events\Domain\AdmissionPass;
use App\Events\Domain\AdmissionWindow;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\AdmissionPassRepository;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\Repository\MemberOfferRepository;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\Contract\MemberAdmissionGuard;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Application\Contract\TokenCodec;
use App\People\Application\Contract\PersonDirectory;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Member self-service. The person is always the caller's own, derived on the server. Lock order is the event,
 * the member's identity link, the person, then the occurrence.
 */
final readonly class MemberEventActions
{
    private const string PREFIX = 'holyspace:event:v1:';
    private const int PASS_SECONDS = 300;

    public function __construct(
        private MemberIdentity $identity,
        private MemberIdentityGuard $guard,
        private MemberAdmissionGuard $admissionGuard,
        private MemberEventReader $reader,
        private MemberOfferRepository $offers,
        private OccurrenceRepository $occurrences,
        private EventRepository $events,
        private RegistrationRepository $registrations,
        private EventRegistrations $bookings,
        private EventAttendance $attendance,
        private AdmissionPassRepository $passes,
        private PersonDirectory $people,
        private CurrentActor $current,
        private PermissionChecker $authorization,
        private TokenCodec $tokens,
        private SessionReader $sessions,
        private AccountDirectory $accounts,
        private EventJournal $journal,
        private MemberBookingUpdates $updates,
        private ClockInterface $clock,
    ) {
    }

    public function configure(string $actorId, string $eventId, bool $enabled, int $expectedVersion): void
    {
        $this->events->findEventForUpdate($eventId) ?? throw EventException::missing('Event');
        EventRules::version($this->reader->offer($eventId)->version, $expectedVersion);
        $this->offers->save($eventId, $enabled, $expectedVersion + 1);
        $this->journal->audit($actorId, 'event.member_offer.changed', $eventId, $enabled ? 'Published to members' : 'Withdrawn from member catalogue', $this->clock->now());
    }

    public function register(string $actorId, string $requestId, string $occurrenceId, ?int $expectedVersion): string
    {
        $this->identity->requirePerson($actorId);
        $before = $this->eventLock($occurrenceId);
        $person = $this->guard->lockPerson($actorId);
        if (!$this->reader->offer($before->eventId)->enabled) {
            throw EventException::missing('Member event');
        }
        $this->identity->requirePerson($actorId);
        $bookingId = $this->bookings->register($actorId, $requestId, $occurrenceId, $person, $expectedVersion, false);
        $booking = $this->own($person, $occurrenceId);
        $this->updates->publish($actorId, $bookingId.':'.$booking->revision->version);

        return $bookingId;
    }

    public function cancel(string $actorId, string $occurrenceId, int $expectedVersion): void
    {
        $this->identity->requirePerson($actorId);
        $this->eventLock($occurrenceId);
        $person = $this->guard->lockPerson($actorId);
        $this->identity->requirePerson($actorId);
        $this->bookings->cancel($actorId, $this->own($person, $occurrenceId)->id, $expectedVersion, 'Cancelled by the member.');
        $booking = $this->own($person, $occurrenceId);
        $this->updates->publish($actorId, $booking->id.':'.$booking->revision->version);
    }

    public function issue(string $actorId, string $occurrenceId): IssuedPass
    {
        $this->identity->requirePerson($actorId);
        $this->eventLock($occurrenceId);
        $person = $this->guard->lockPerson($actorId);
        $member = $this->admissionGuard->lock($actorId) ?? throw EventException::missing('Member');
        $entry = $this->people->lock($person);
        if (null === $entry || !$entry->available) {
            throw EventException::missing('Person');
        }
        $event = $this->occurrences->findForUpdate($occurrenceId) ?? throw EventException::missing('Member event');
        $this->identity->requirePerson($actorId);
        $booking = $this->own($person, $occurrenceId);
        if ('CONFIRMED' !== $booking->status) {
            throw EventException::conflict('A confirmed booking is required.');
        }
        $now = $this->clock->now();
        AdmissionWindow::require($event, $now);
        $token = $this->tokens->generate();
        $expiry = $now->modify('+'.self::PASS_SECONDS.' seconds');
        if ($expiry > $event->endsAt) {
            $expiry = $event->endsAt;
        }
        $sessionId = ($this->current->current() ?? throw EventException::missing('Session'))->sessionId;
        $this->passes->insert(new AdmissionPass($this->tokens->hash($token), $booking->id, $actorId, $sessionId, $member->version, $booking->revision->version, $event->scheduleRevision, $expiry));

        return new IssuedPass(self::PREFIX.$token, $expiry);
    }

    /** @return string the attendance id */
    public function scan(string $actorId, string $occurrenceId, string $requestId, ?string $credential): string
    {
        if (null === $credential || 1 !== preg_match('/\Aholyspace:event:v1:[A-Za-z0-9_-]{20,200}\z/', $credential)) {
            throw EventException::invalid('Scan a Holyspace event attendance code.');
        }
        $hash = $this->tokens->hash(substr($credential, \strlen(self::PREFIX)));
        $before = $this->passes->find($hash) ?? throw EventException::missing('Attendance pass');
        $this->eventLock($occurrenceId);
        $member = $this->admissionGuard->lock($before->accountId) ?? throw EventException::conflict('Member access is no longer active.');
        $booking = $this->registrations->findRegistration($before->registrationId) ?? throw EventException::missing('Registration');
        if ($booking->occurrenceId !== $occurrenceId || $booking->personId !== $member->personId) {
            throw EventException::conflict('This code does not belong to this gathering.');
        }
        $entry = $this->people->lock($member->personId);
        if (null === $entry || !$entry->available) {
            throw EventException::missing('Person');
        }
        $event = $this->occurrences->findForUpdate($occurrenceId) ?? throw EventException::missing('Occurrence');
        $pass = $this->passes->lock($hash) ?? throw EventException::missing('Attendance pass');
        $this->authorization->requireActorPermission($actorId, 'events.attendance.manage');
        $now = $this->clock->now();
        // Re-read under the locks: the booking may have changed since the unlocked read above.
        $current = $this->reload($before->registrationId);
        if (!$this->valid($pass, $member->version, $current, $event, $now)) {
            throw EventException::conflict('This attendance code expired or changed. Ask the member to refresh it.');
        }
        $arrival = $this->attendance->checkIn($actorId, $requestId, $current->id, $pass->registrationVersion);
        $this->passes->markUsed($hash, $now);

        return $arrival;
    }

    private function valid(AdmissionPass $pass, int $memberVersion, EventRegistration $booking, EventOccurrence $event, DateTimeImmutable $now): bool
    {
        if (null !== $pass->usedAt || $pass->expiresAt <= $now || $memberVersion !== $pass->memberVersion
            || $booking->revision->version !== $pass->registrationVersion || $event->scheduleRevision !== $pass->scheduleRevision
            || !$this->accounts->active($pass->accountId)) {
            return false;
        }
        $session = $this->sessions->findById($pass->sessionId);

        return null !== $session && $session->accountId === $pass->accountId && $session->expiresAt > $now;
    }

    private function reload(string $registrationId): EventRegistration
    {
        return $this->registrations->findRegistration($registrationId) ?? throw EventException::missing('Registration');
    }

    /** Locks the event of the occurrence before anything else, so offers and bookings serialise per event. */
    private function eventLock(string $occurrenceId): EventOccurrence
    {
        $occurrence = $this->occurrences->findUnlocked($occurrenceId) ?? throw EventException::missing('Member event');
        $this->events->findEventForUpdate($occurrence->eventId) ?? throw EventException::missing('Event');

        return $occurrence;
    }

    private function own(string $personId, string $occurrenceId): EventRegistration
    {
        return $this->registrations->findRegistrationOfPerson($occurrenceId, $personId) ?? throw EventException::missing('Booking');
    }
}
