<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\VenueReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\EventSchedule;
use Psr\Clock\ClockInterface;

/** Computes what a draft would schedule and the hash a later apply call must present. Never writes. */
final readonly class ScheduleReview
{
    public function __construct(
        private VenueReader $venues,
        private OccurrenceReader $occurrences,
        private ClockInterface $clock,
    ) {
    }

    public function preview(?ScheduleDraft $draft): SchedulePreview
    {
        if (null === $draft) {
            throw EventException::invalid('Choose a schedule.');
        }
        EventRules::id($draft->venueId);
        $venue = $this->venues->find($draft->venueId);
        if (null === $venue || !$venue->active) {
            throw EventException::missing('Active venue');
        }
        $dates = EventSchedule::preview($draft->series('00000000-0000-0000-0000-000000000000', '00000000-0000-0000-0000-000000000000'));
        $now = $this->clock->now();
        foreach ($dates as $date) {
            if ($date->startsAt <= $now) {
                throw EventException::invalid('New occurrences must start in the future.');
            }
            if ($venue->exclusive && $this->occurrences->venueClash($venue->id, $date->startsAt, $date->endsAt, [])) {
                throw EventException::conflict('The exclusive venue is already reserved during a selected date.');
            }
        }
        if ($venue->exclusive) {
            for ($index = 1; $index < \count($dates); ++$index) {
                if ($dates[$index]->startsAt < $dates[$index - 1]->endsAt) {
                    throw EventException::conflict('The recurring dates overlap in the exclusive venue.');
                }
            }
        }

        return new SchedulePreview(
            Fingerprint::of(JavaText::draft($draft), JavaText::venue($venue), JavaText::windows($dates)),
            $venue,
            $dates,
        );
    }

    /** The caller must have reviewed exactly this preview. */
    public function confirm(SchedulePreview $preview, ?string $reviewedHash): void
    {
        if ($preview->hash !== $reviewedHash) {
            throw EventException::conflict('The reviewed schedule changed. Preview the current details before applying.');
        }
    }
}
