<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\OccurrenceScheduleListener;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\Events\Domain\Repository\VenueRepository;
use Psr\Clock\ClockInterface;

/**
 * Applies a reviewed change. Locks are taken in the documented order (event, series, venues, occurrences,
 * the last two sorted by id) and the plan is recomputed under them, so a stale preview is refused.
 */
final readonly class OccurrenceChanges
{
    private const int LOOKAHEAD = 105;

    public function __construct(
        private EventRepository $events,
        private VenueRepository $venues,
        private OccurrenceRepository $occurrences,
        private RegistrationRepository $registrations,
        private OccurrenceChangeReview $review,
        private EventJournal $journal,
        private ClockInterface $clock,
        /** @var iterable<OccurrenceScheduleListener> */
        private iterable $listeners = [],
    ) {
    }

    public function apply(string $actorId, string $action, ?OccurrenceChangeDraft $draft, ?string $reviewHash, string $reason): void
    {
        $note = EventRules::text($reason, 1000);
        if (null === $draft || $action !== $draft->action) {
            throw EventException::invalid('Use the matching explicit action.');
        }
        $before = $this->occurrences->findUnlocked($draft->occurrenceId) ?? throw EventException::missing('Occurrence');
        $this->events->findEventForUpdate($before->eventId) ?? throw EventException::missing('Event');
        $series = $this->events->findSeriesForUpdate($before->seriesId) ?? throw EventException::missing('Series');
        $now = $this->clock->now();
        $candidates = 'FUTURE' === $draft->scope ? $this->occurrences->findFutureOfSeries($series->id, $before->originDate, $now, self::LOOKAHEAD) : [$before];
        $venueIds = array_map(static fn ($o): string => $o->venueId, $candidates);
        if (null !== $draft->venueId) {
            $venueIds[] = $draft->venueId;
        }
        foreach (self::sorted($venueIds) as $id) {
            $this->venues->findForUpdate($id) ?? throw EventException::missing('Venue');
        }
        foreach (self::sorted(array_map(static fn ($o): string => $o->id, $candidates)) as $id) {
            $this->occurrences->findForUpdate($id) ?? throw EventException::missing('Occurrence');
        }

        $plan = $this->review->plan($draft);
        $this->review->confirm($plan, $reviewHash);
        foreach ($plan->changes as $change) {
            $after = $change->after;
            $this->occurrences->save($after);
            foreach ($this->listeners as $listener) {
                $listener->changed($after->id, $after->startsAt);
            }
            if ('CANCEL' === $action) {
                $this->registrations->cancelOccurrenceRegistrations($after->id, $actorId, $note, $now);
                $policy = $this->registrations->findPolicy($after->id) ?? throw EventException::missing('Registration policy');
                $this->registrations->savePolicy($policy->configure(false, $policy->capacity, $policy->waitlistEnabled));
            }
            $this->journal->occurrence($actorId, $action, $after, $note, $now);
        }
        $updated = 'RESCHEDULE' === $action && 'FUTURE' === $draft->scope
            ? $series->defaults(
                $draft->startsAt ?? throw EventException::invalid('Choose a date, times and end day offset of 0–7.'),
                $draft->endsAt ?? throw EventException::invalid('Choose a date, times and end day offset of 0–7.'),
                $draft->endDayOffset,
                $draft->venueId ?? throw EventException::missing('Active venue'),
            )
            : $series->touch();
        $this->events->saveSeries($updated);
        $this->journal->audit($actorId, 'occurrences.'.strtolower($action), $before->eventId, $note, $now);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string> distinct ids in ascending string order
     */
    private static function sorted(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $ids;
    }
}
