<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\AttendanceCounts;
use App\Events\Application\Contract\NoticeTotals;
use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Application\Contract\VenueView;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\ScheduleWindow;
use App\Events\Domain\Venue;

/**
 * The text Java's String.valueOf gives the record types that feed schedule hashes. Preview hashes are
 * exchanged between a preview and an apply call, possibly served by different backends, so the
 * wording (field order, separators, formats) must not drift.
 */
final class JavaText
{
    private function __construct()
    {
    }

    public static function draft(?ScheduleDraft $draft): ?string
    {
        if (null === $draft) {
            return null;
        }

        return sprintf(
            'Draft[venueId=%s, timeZone=%s, frequency=%s, interval=%d, count=%d, firstDate=%s, startsAt=%s, endsAt=%s, endDayOffset=%d]',
            $draft->venueId,
            $draft->timeZone,
            $draft->frequency,
            $draft->interval,
            $draft->count,
            $draft->firstDate->iso(),
            $draft->startsAt->javaString(),
            $draft->endsAt->javaString(),
            $draft->endDayOffset,
        );
    }

    public static function venue(Venue|VenueView|null $venue): string
    {
        if (null === $venue) {
            return 'null';
        }

        return sprintf(
            'Venue[id=%s, name=%s, address=%s, exclusive=%s, active=%s, version=%d]',
            $venue->id,
            $venue->name,
            $venue->address,
            $venue->exclusive ? 'true' : 'false',
            $venue->active ? 'true' : 'false',
            $venue->version,
        );
    }

    /** @param list<ScheduleWindow> $windows */
    public static function windows(array $windows): string
    {
        return '['.implode(', ', array_map(
            static fn (ScheduleWindow $window): string => sprintf(
                'Window[originDate=%s, startsAt=%s, endsAt=%s]',
                $window->originDate->iso(),
                Fingerprint::instant($window->startsAt),
                Fingerprint::instant($window->endsAt),
            ),
            $windows,
        )).']';
    }

    public static function changeDraft(OccurrenceChangeDraft $draft): string
    {
        return sprintf(
            'Draft[occurrenceId=%s, expectedVersion=%d, action=%s, scope=%s, date=%s, startsAt=%s, endsAt=%s, endDayOffset=%d, venueId=%s]',
            $draft->occurrenceId,
            $draft->expectedVersion,
            $draft->action ?? 'null',
            $draft->scope ?? 'null',
            $draft->date?->iso() ?? 'null',
            $draft->startsAt?->javaString() ?? 'null',
            $draft->endsAt?->javaString() ?? 'null',
            $draft->endDayOffset,
            $draft->venueId ?? 'null',
        );
    }

    public static function occurrence(EventOccurrence $o): string
    {
        return sprintf(
            'EventOccurrence[id=%s, eventId=%s, seriesId=%s, originDate=%s, timeZone=%s, startsAt=%s, endsAt=%s, venueId=%s, venueName=%s, venueAddress=%s, exclusive=%s, status=%s, overridden=%s, version=%d, scheduleRevision=%d]',
            $o->id,
            $o->eventId,
            $o->seriesId,
            $o->originDate->iso(),
            $o->timeZone,
            Fingerprint::instant($o->startsAt),
            Fingerprint::instant($o->endsAt),
            $o->venueId,
            $o->venueName,
            $o->venueAddress,
            $o->exclusive ? 'true' : 'false',
            $o->status,
            $o->overridden ? 'true' : 'false',
            $o->version,
            $o->scheduleRevision,
        );
    }

    public static function counts(RegistrationCounts $counts): string
    {
        return sprintf('Counts[confirmed=%d, waitlisted=%d, cancelled=%d]', $counts->confirmed, $counts->waitlisted, $counts->cancelled);
    }

    /** @param list<OccurrenceChange> $changes */
    public static function changes(array $changes): string
    {
        return '['.implode(', ', array_map(
            static fn (OccurrenceChange $c): string => sprintf(
                'Item[before=%s, after=%s, registrations=%s, registrationVersion=%d, attended=%d]',
                self::occurrence($c->before),
                self::occurrence($c->after),
                self::counts($c->registrations),
                $c->registrationVersion,
                $c->attended,
            ),
            $changes,
        )).']';
    }

    /** @param list<string> $ids */
    public static function ids(array $ids): string
    {
        return '['.implode(', ', $ids).']';
    }

    public static function attendanceCounts(AttendanceCounts $c): string
    {
        return sprintf('Counts[confirmed=%d, waitlisted=%d, cancelled=%d, attended=%d, absent=%d]', $c->confirmed, $c->waitlisted, $c->cancelled, $c->attended, $c->absent);
    }

    public static function notices(NoticeTotals $n): string
    {
        return sprintf(
            'Notices[approved=%d, submitted=%d, delivered=%d, pending=%d, failed=%d, captured=%d, cancelled=%d, remindersApproved=%d, complete=%s]',
            $n->approved,
            $n->submitted,
            $n->delivered,
            $n->pending,
            $n->failed,
            $n->captured,
            $n->cancelled,
            $n->remindersApproved,
            $n->complete ? 'true' : 'false',
        );
    }
}
