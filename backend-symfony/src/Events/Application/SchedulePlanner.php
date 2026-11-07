<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventSeries;
use App\Events\Domain\RegistrationPolicy;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\Events\Domain\Venue;
use App\Shared\Application\IdGenerator;
use DateTimeImmutable;

/** Writes the occurrences a reviewed preview promised, each with a closed registration policy and history row. */
final readonly class SchedulePlanner
{
    public function __construct(
        private OccurrenceRepository $occurrences,
        private RegistrationRepository $registrations,
        private EventJournal $journal,
        private IdGenerator $ids,
    ) {
    }

    public function insertDates(string $actorId, EventSeries $series, SchedulePreview $preview, Venue $venue, DateTimeImmutable $now): void
    {
        foreach ($preview->dates as $window) {
            $occurrence = EventOccurrence::schedule($this->ids->uuid(), $series, $window, $venue);
            $this->occurrences->insert($occurrence);
            $this->registrations->insertPolicy(RegistrationPolicy::initial($occurrence->id));
            $this->journal->occurrence($actorId, 'SCHEDULED', $occurrence, 'Occurrence scheduled.', $now);
        }
    }
}
