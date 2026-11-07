<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventJournalReader;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\JournalRequest;
use App\Events\Domain\AdmissionWindow;
use App\Events\Domain\Attendance;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\AttendanceRepository;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/** Check-in, walk-ins and corrections. The person row is locked before the occurrence, as for bookings. */
final readonly class EventAttendance
{
    public function __construct(
        private AttendanceRepository $attendance,
        private AttendanceReader $reader,
        private RegistrationRepository $registrations,
        private OccurrenceRepository $occurrences,
        private EventReader $events,
        private PersonDirectory $people,
        private EventRegistrations $bookings,
        private EventJournal $journal,
        private EventJournalReader $requests,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the attendance id (the original one when the request is repeated) */
    public function checkIn(string $actorId, string $requestId, string $registrationId, int $expectedVersion): string
    {
        EventRules::id($requestId);
        $hash = Fingerprint::of($actorId, $registrationId, $expectedVersion);
        $before = $this->registration($registrationId);
        $occurrence = $this->lock($before);
        $previous = $this->requests->request($requestId);
        if (null !== $previous) {
            if ('CHECK_IN' !== $previous->kind || $previous->hash !== $hash) {
                throw EventException::conflict('This request identifier was already used.');
            }

            return $previous->targetId;
        }
        $current = $this->registration($registrationId);
        EventRules::version($current->revision->version, $expectedVersion);
        $this->requireAdmission($current, $occurrence);
        if (null !== $this->attendance->findByRegistration($registrationId)) {
            throw EventException::conflict('Attendance already exists. Use its versioned correction action.');
        }
        $arrival = $this->arrive($actorId, $current);
        $this->journal->record(new JournalRequest($requestId, 'CHECK_IN', $hash, $arrival->id, $actorId, $this->clock->now()));

        return $arrival->id;
    }

    public function walkIn(string $actorId, string $requestId, string $occurrenceId, string $personId, ?int $expectedVersion): WalkIn
    {
        $repeated = null !== $this->requests->request($requestId);
        $registrationId = $this->bookings->register($actorId, $requestId, $occurrenceId, $personId, $expectedVersion, true);
        $booking = $this->registration($registrationId);
        if (!$repeated && 'CONFIRMED' === $booking->status) {
            $occurrence = $this->occurrences->findForUpdate($occurrenceId) ?? throw EventException::missing('Occurrence');
            $this->requireAdmission($booking, $occurrence);
            $this->arrive($actorId, $booking);
        }

        return new WalkIn($registrationId, $this->reader->isPresent($registrationId), $booking->status);
    }

    public function correct(string $actorId, string $registrationId, int $expectedVersion, ?string $status, string $reason): void
    {
        $note = EventRules::text($reason, 1000);
        $booking = $this->registration($registrationId);
        $occurrence = $this->lock($booking);
        $current = $this->attendance->findByRegistration($registrationId) ?? throw EventException::missing('Attendance');
        EventRules::version($current->version, $expectedVersion);
        if (!\in_array($status, ['PRESENT', 'VOIDED'], true)) {
            throw EventException::invalid('Choose present or voided attendance.');
        }
        $now = $this->clock->now();
        if ('PRESENT' === $status && $occurrence->endsAt > $now) {
            $this->requireAdmission($this->registration($registrationId), $occurrence);
        }
        if ($status !== $current->status) {
            $this->attendance->save($current->correct($status, $expectedVersion));
            $this->attendance->recordHistory($current->id, $actorId, 'PRESENT' === $status ? 'REINSTATED' : 'VOIDED', $note, $now);
        }
    }

    private function registration(string $id): EventRegistration
    {
        return $this->registrations->findRegistration($id) ?? throw EventException::missing('Registration');
    }

    private function lock(EventRegistration $registration): EventOccurrence
    {
        $this->people->lock($registration->personId) ?? throw EventException::missing('Person');

        return $this->occurrences->findForUpdate($registration->occurrenceId) ?? throw EventException::missing('Occurrence');
    }

    private function requireAdmission(EventRegistration $registration, EventOccurrence $occurrence): void
    {
        AdmissionWindow::require($occurrence, $this->clock->now());
        $eventActive = $this->events->find($occurrence->eventId)->active ?? false;
        $available = $this->people->get($registration->personId)->available ?? false;
        if ('CONFIRMED' !== $registration->status || !$eventActive || !$available) {
            throw EventException::conflict('Check-in requires a confirmed booking and an available person on an active event.');
        }
    }

    private function arrive(string $actorId, EventRegistration $registration): Attendance
    {
        $now = $this->clock->now();
        $arrival = Attendance::arrive($this->ids->uuid(), $registration->id, $now);
        $this->attendance->insert($arrival);
        $this->attendance->recordHistory($arrival->id, $actorId, 'CHECKED_IN', 'Arrival recorded.', $now);

        return $arrival;
    }
}
