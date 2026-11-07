<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceMovementPolicy;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventRules;
use App\Events\Domain\EventSchedule;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\VenueRepository;
use App\Events\Domain\Venue;
use Psr\Clock\ClockInterface;

/** Works out exactly what a reschedule, cancel or restore would change, without writing anything. */
final readonly class OccurrenceChangeReview
{
    private const int MAX_DATES = 104;

    public function __construct(
        private EventReader $events,
        private VenueRepository $venues,
        private OccurrenceRepository $occurrences,
        private OccurrenceReader $clashes,
        private RegistrationReader $registrations,
        private AttendanceReader $attendance,
        private ClockInterface $clock,
        /** @var iterable<OccurrenceMovementPolicy> */
        private iterable $movementPolicies = [],
    ) {
    }

    public function plan(OccurrenceChangeDraft $draft): OccurrenceChangePlan
    {
        $action = $draft->action;
        if (!\in_array($action, ['RESCHEDULE', 'CANCEL', 'RESTORE'], true) || !\in_array($draft->scope, ['THIS', 'FUTURE'], true)
            || ('RESTORE' === $action && 'THIS' !== $draft->scope)) {
            throw EventException::invalid('Choose reschedule, cancel or restore and an explicit scope. Restoration applies to one occurrence.');
        }
        $pivot = $this->occurrences->findUnlocked($draft->occurrenceId) ?? throw EventException::missing('Occurrence');
        EventRules::version($pivot->version, $draft->expectedVersion);
        $event = $this->events->find($pivot->eventId);
        if (null === $event || !$event->active) {
            throw EventException::conflict('The event is archived.');
        }
        $series = $this->events->findSeries($pivot->seriesId) ?? throw EventException::missing('Series');
        $now = $this->clock->now();
        if ($pivot->endsAt <= $now || ('CANCEL' !== $action && $pivot->startsAt <= $now)) {
            throw EventException::conflict('Historical or already-started scheduling cannot be rewritten.');
        }
        if (('RESTORE' === $action) !== ('CANCELLED' === $pivot->status)) {
            throw EventException::conflict('Choose an action matching the current occurrence state.');
        }
        $candidates = 'THIS' === $draft->scope ? [$pivot] : $this->occurrences->findFutureOfSeries($series->id, $pivot->originDate, $now, self::MAX_DATES + 1);
        if (\count($candidates) > self::MAX_DATES) {
            throw EventException::conflict('Review at most 104 dates together.');
        }
        $target = null;
        if ('RESCHEDULE' === $action) {
            $target = null === $draft->venueId ? null : $this->venues->findUnlocked($draft->venueId);
            if (null === $target || !$target->active) {
                throw EventException::missing('Active venue');
            }
        }

        $skipped = [];
        $changes = [];
        foreach ($candidates as $current) {
            if (('RESCHEDULE' === $action && ('SCHEDULED' !== $current->status || $current->startsAt <= $now || ('FUTURE' === $draft->scope && $current->overridden)))
                || ('CANCEL' === $action && 'CANCELLED' === $current->status)) {
                $skipped[] = $current->id;
                continue;
            }
            if ('RESCHEDULE' === $action && !$this->movable($current->id)) {
                throw EventException::conflict('A linked group has prepared its dated roster. Cancel this gathering instead of moving its attendance evidence.');
            }
            $attended = $this->attendance->attended($current->id);
            if ('RESCHEDULE' === $action && $attended > 0) {
                throw EventException::conflict('People have already checked in. Correct mistaken arrivals or cancel the occurrence instead of rescheduling it.');
            }
            $changed = $this->apply($action, $draft, $current, $target);
            if ('CANCEL' !== $action && $changed->startsAt <= $now) {
                throw EventException::invalid('The revised occurrence must start in the future.');
            }
            $policy = $this->registrations->policy($current->id) ?? throw EventException::missing('Registration policy');
            $changes[] = new OccurrenceChange($current, $changed, $this->registrations->counts($current->id), $policy->version, $attended);
        }
        if ([] === $changes) {
            throw EventException::conflict('No eligible dates remain. Individually edited and cancelled dates are preserved when rescheduling a future series.');
        }
        $this->checkVenue($changes);

        return new OccurrenceChangePlan(
            Fingerprint::of(JavaText::changeDraft($draft), $series->version, JavaText::venue($target), JavaText::changes($changes), JavaText::ids($skipped)),
            $changes,
            $skipped,
            array_sum(array_map(static fn (OccurrenceChange $c): int => $c->registrations->confirmed, $changes)),
            array_sum(array_map(static fn (OccurrenceChange $c): int => $c->registrations->waitlisted, $changes)),
            array_sum(array_map(static fn (OccurrenceChange $c): int => $c->attended, $changes)),
        );
    }

    /** Every context that attached evidence to the occurrence must agree it can move. */
    private function movable(string $occurrenceId): bool
    {
        foreach ($this->movementPolicies as $policy) {
            if (!$policy->movable($occurrenceId)) {
                return false;
            }
        }

        return true;
    }

    private function apply(string $action, OccurrenceChangeDraft $draft, EventOccurrence $current, ?Venue $target): EventOccurrence
    {
        return match ($action) {
            'RESCHEDULE' => $current->reschedule(
                EventSchedule::window(
                    'THIS' === $draft->scope ? ($draft->date ?? throw EventException::invalid('Choose a date, times and end day offset of 0–7.')) : $current->originDate,
                    $draft->startsAt ?? throw EventException::invalid('Choose a date, times and end day offset of 0–7.'),
                    $draft->endsAt ?? throw EventException::invalid('Choose a date, times and end day offset of 0–7.'),
                    $draft->endDayOffset,
                    $current->timeZone,
                ),
                $target ?? throw EventException::missing('Active venue'),
                'THIS' === $draft->scope,
            ),
            'CANCEL' => $current->cancel(),
            default => $current->restore(),
        };
    }

    /** @param list<OccurrenceChange> $changes */
    private function checkVenue(array $changes): void
    {
        $ids = array_map(static fn (OccurrenceChange $c): string => $c->before->id, $changes);
        $scheduled = array_values(array_filter(
            array_map(static fn (OccurrenceChange $c): EventOccurrence => $c->after, $changes),
            static fn (EventOccurrence $o): bool => 'SCHEDULED' === $o->status,
        ));
        usort($scheduled, static fn (EventOccurrence $a, EventOccurrence $b): int => $a->startsAt <=> $b->startsAt);
        foreach ($scheduled as $o) {
            $venue = $this->venues->findUnlocked($o->venueId);
            if (null === $venue || !$venue->active) {
                throw EventException::conflict('The recorded venue is archived. Reopen that venue before restoring this occurrence.');
            }
            if ($o->exclusive && $this->clashes->venueClash($o->venueId, $o->startsAt, $o->endsAt, $ids)) {
                throw EventException::conflict('The exclusive venue is already reserved during a revised date.');
            }
        }
        $count = \count($scheduled);
        for ($a = 0; $a < $count; ++$a) {
            for ($b = $a + 1; $b < $count; ++$b) {
                if ($scheduled[$a]->exclusive && $scheduled[$a]->venueId === $scheduled[$b]->venueId
                    && EventRules::overlaps($scheduled[$a]->startsAt, $scheduled[$a]->endsAt, $scheduled[$b]->startsAt, $scheduled[$b]->endsAt)) {
                    throw EventException::conflict('The revised dates overlap in the exclusive venue.');
                }
            }
        }
    }

    public function confirm(OccurrenceChangePlan $plan, ?string $reviewedHash): void
    {
        if ($plan->hash !== $reviewedHash) {
            throw EventException::conflict('The reviewed schedule changed. Preview the current details before applying.');
        }
    }
}
