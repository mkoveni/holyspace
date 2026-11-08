<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\ActivitySource;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;

/** Resolves a shift's linked activity through the contexts that published an {@see ActivitySource}. */
final readonly class ActivityRegistry
{
    /** @var list<ActivitySource> */
    private array $sources;

    /** @param iterable<ActivitySource> $sources */
    public function __construct(iterable $sources)
    {
        $this->sources = array_values([...$sources]);
    }

    /** @throws VolunteerException when the linked activity is gone, inactive, unknown or was rescheduled */
    public function require(ShiftView $shift): void
    {
        if ('STANDALONE' === $shift->sourceKind) {
            return;
        }
        if ($this->revision($shift) !== $shift->sourceRevision) {
            throw VolunteerException::conflict('The linked activity was rescheduled. Review the duty and update the shift before proceeding.');
        }
    }

    /** The published activity a shift is linked to, or null for a standalone shift or an unknown source. */
    public function find(string $kind, ?string $id): ?SourceActivity
    {
        $source = $this->source($kind);

        return null === $source || null === $id ? null : $source->find($id);
    }

    /** @return list<SourceActivity> the source's activities starting in the window; none for an unknown kind */
    public function upcoming(string $kind, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        return $this->source($kind)?->upcoming($from, $until, $limit, $offset) ?? [];
    }

    private function source(string $kind): ?ActivitySource
    {
        foreach ($this->sources as $candidate) {
            if ($candidate->kind() === $kind) {
                return $candidate;
            }
        }

        return null;
    }

    public function revision(ShiftView $shift): ?string
    {
        if ('STANDALONE' === $shift->sourceKind) {
            return null;
        }
        $source = $this->source($shift->sourceKind);
        if (null === $source) {
            throw VolunteerException::invalid('This activity source is not available in the Java application.');
        }
        $activity = null === $shift->sourceId ? null : $source->find($shift->sourceId);
        if (null === $activity || !$activity->active) {
            throw VolunteerException::conflict('The linked activity is cancelled, closed or unavailable.');
        }
        $starts = Fingerprint::instant($activity->startsAt);
        $ends = Fingerprint::instant($activity->endsAt);

        return null === $activity->scheduleRevision
            ? Fingerprint::of($activity->id, $starts, $ends)
            : Fingerprint::of($activity->id, $starts, $ends, $activity->scheduleRevision, $activity->venueLabel);
    }
}
