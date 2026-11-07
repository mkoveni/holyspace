<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventJournalReader;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\JournalRequest;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Domain\AdmissionWindow;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\EventRules;
use App\Events\Domain\RegistrationPolicy;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Registration policy, booking and cancellation for one occurrence. A person's row is locked before the
 * occurrence, and the occurrence lock serialises capacity, queue positions and waitlist promotion.
 */
final readonly class EventRegistrations
{
    public function __construct(
        private EventReader $events,
        private OccurrenceRepository $occurrences,
        private RegistrationRepository $registrations,
        private RegistrationReader $reader,
        private AttendanceReader $attendance,
        private PersonDirectory $people,
        private EventJournal $journal,
        private EventJournalReader $requests,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function configure(string $actorId, string $occurrenceId, int $expectedVersion, bool $open, ?int $capacity, bool $waitlist, string $reason): void
    {
        $note = EventRules::text($reason, 1000);
        $occurrence = $this->lock($occurrenceId);
        $policy = $this->registrations->findPolicy($occurrenceId) ?? throw EventException::missing('Registration policy');
        EventRules::version($policy->version, $expectedVersion);
        $now = $this->clock->now();
        if ($open && ('SCHEDULED' !== $occurrence->status || $occurrence->endsAt <= $now || !$this->eventActive($occurrence))) {
            throw EventException::conflict('Registration settings require an active scheduled occurrence that has not ended.');
        }
        if (null !== $capacity && $capacity < $this->reader->counts($occurrenceId)->confirmed) {
            throw EventException::conflict('Capacity cannot be below the confirmed registrations.');
        }
        $changed = $policy->configure($open, $capacity, $waitlist);
        $this->registrations->savePolicy($changed);
        if ('SCHEDULED' === $occurrence->status && $occurrence->endsAt > $now) {
            $this->promote($actorId, $changed);
        }
        $this->journal->audit($actorId, 'registration.policy.updated', $occurrenceId, $note, $now);
    }

    /** @return string the registration id (the original one when the request is repeated) */
    public function register(string $actorId, string $requestId, string $occurrenceId, string $personId, ?int $expectedVersion, bool $atDoor): string
    {
        EventRules::id($requestId);
        EventRules::id($personId);
        $kind = $atDoor ? 'REGISTER_DOOR' : 'REGISTER';
        $hash = Fingerprint::of($actorId, $occurrenceId, $personId, $expectedVersion);

        $person = $this->people->lock($personId);
        if (null === $person || !$person->available) {
            throw EventException::missing('Available person');
        }
        $occurrence = $this->lock($occurrenceId);
        $previous = $this->requests->request($requestId);
        if (null !== $previous) {
            if ($previous->kind !== $kind || $previous->hash !== $hash) {
                throw EventException::conflict('The registration request identifier was already used.');
            }

            return $previous->targetId;
        }
        $now = $this->clock->now();
        if ($atDoor) {
            AdmissionWindow::require($occurrence, $now);
            if (!$this->eventActive($occurrence)) {
                throw EventException::conflict('The event is archived.');
            }
        } else {
            $this->requireBookable($occurrence);
        }
        $policy = $this->registrations->findPolicy($occurrenceId) ?? throw EventException::missing('Registration policy');
        if (!$policy->open) {
            throw EventException::conflict('Registration is closed.');
        }
        $old = $this->registrations->findRegistrationOfPerson($occurrenceId, $personId);
        if (null !== $old) {
            if (null === $expectedVersion) {
                throw EventException::conflict('This person already has a record. Use its versioned re-registration action.');
            }
            EventRules::version($old->revision->version, $expectedVersion);
            if ('CANCELLED' !== $old->status) {
                throw EventException::conflict('This person is already registered.');
            }
        }
        $this->promote($actorId, $policy);
        $full = null !== $policy->capacity && $this->reader->counts($occurrenceId)->confirmed >= $policy->capacity;
        if ($full && !$policy->waitlistEnabled) {
            throw EventException::conflict('The occurrence is full and its waitlist is disabled.');
        }
        $allocated = $policy->allocate();
        if (null === $old) {
            $registration = EventRegistration::register($this->ids->uuid(), $occurrenceId, $personId, $full, $allocated->nextPosition, $now);
            $this->registrations->insertRegistration($registration);
        } else {
            $registration = $old->rejoin($full, $allocated->nextPosition, $now);
            $this->registrations->saveRegistration($registration);
        }
        $this->registrations->savePolicy($allocated);
        $this->journal->registration($actorId, 'REGISTERED', $registration->id, $registration->status, $now);
        $this->journal->record(new JournalRequest($requestId, $kind, $hash, $registration->id, $actorId, $now));

        return $registration->id;
    }

    public function cancel(string $actorId, string $registrationId, int $expectedVersion, string $reason): void
    {
        $note = EventRules::text($reason, 1000);
        $before = $this->registrations->findRegistration($registrationId) ?? throw EventException::missing('Registration');
        $this->people->lock($before->personId) ?? throw EventException::missing('Person');
        $occurrence = $this->lock($before->occurrenceId);
        $now = $this->clock->now();
        if ($occurrence->endsAt <= $now) {
            throw EventException::conflict('Historical registration cannot be rewritten.');
        }
        $current = $this->reload($registrationId);
        EventRules::version($current->revision->version, $expectedVersion);
        if ($this->attendance->isPresent($registrationId)) {
            throw EventException::conflict("Correct the person's attendance before cancelling this booking.");
        }
        if ('CANCELLED' === $current->status) {
            return;
        }
        $this->registrations->saveRegistration($current->cancel($now));
        $policy = $this->registrations->findPolicy($occurrence->id) ?? throw EventException::missing('Registration policy');
        if ('SCHEDULED' === $occurrence->status) {
            $this->promote($actorId, $policy);
        }
        $this->registrations->savePolicy($policy->touch());
        $this->journal->registration($actorId, 'CANCELLED', $registrationId, $note, $now);
    }

    /** Reads the registration again after the person and occurrence are locked, so the version check sees committed state. */
    private function reload(string $registrationId): EventRegistration
    {
        return $this->registrations->findRegistration($registrationId) ?? throw EventException::missing('Registration');
    }

    private function lock(string $occurrenceId): EventOccurrence
    {
        return $this->occurrences->findForUpdate($occurrenceId) ?? throw EventException::missing('Occurrence');
    }

    private function eventActive(EventOccurrence $occurrence): bool
    {
        return $this->events->find($occurrence->eventId)->active ?? false;
    }

    private function requireBookable(EventOccurrence $occurrence): void
    {
        if ('SCHEDULED' !== $occurrence->status || $occurrence->startsAt <= $this->clock->now() || !$this->eventActive($occurrence)) {
            throw EventException::conflict('Registration requires an active event and a scheduled occurrence that has not started.');
        }
    }

    /** Fills free places from the waitlist in queue order. */
    private function promote(string $actorId, RegistrationPolicy $policy): void
    {
        $confirmed = $this->reader->counts($policy->occurrenceId)->confirmed;
        $places = null === $policy->capacity ? \PHP_INT_MAX : max(0, $policy->capacity - $confirmed);
        $this->registrations->promote($policy->occurrenceId, min($places, 1_000_000), $actorId, $this->clock->now());
    }
}
