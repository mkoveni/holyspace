<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Events\Application\Contract\CalendarEntry;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Volunteering\Application\Contract\ActivitySource;
use App\Volunteering\Application\Contract\ServingActivityLocks;
use App\Volunteering\Application\Contract\SourceActivity;
use DateTimeImmutable;

/** Publishes event occurrences to Volunteering as staffable activities, and locks them for staffing decisions. */
final readonly class PublishedEventActivities implements ActivitySource, ServingActivityLocks
{
    private const string KIND = 'EVENT_OCCURRENCE';
    private const int PAGE = 100;

    public function __construct(
        private OccurrenceReader $occurrences,
        private OccurrenceRepository $repository,
    ) {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function find(string $id): ?SourceActivity
    {
        $entry = $this->occurrences->entry($id);

        return null === $entry ? null : self::activity($entry);
    }

    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until, ?int $limit = null, int $offset = 0): array
    {
        EventRules::range($from, $until);
        $activities = [];
        $position = $offset;
        do {
            $size = null === $limit ? self::PAGE : min(self::PAGE, $limit - \count($activities));
            if ($size < 1) {
                break;
            }
            $page = $this->occurrences->calendar(null, '', $from, $until, 'SCHEDULED', $size, $position);
            foreach ($page as $entry) {
                $activity = self::activity($entry);
                if ($activity->active) {
                    $activities[] = $activity;
                }
            }
            $position += \count($page);
        } while (\count($page) === $size && (null === $limit || \count($activities) < $limit));

        return $activities;
    }

    /** Occurrences only, de-duplicated and in ascending id order, so concurrent staffing decisions cannot deadlock. */
    public function lock(array $activities): void
    {
        $ids = [];
        foreach ($activities as $activity) {
            if (self::KIND === $activity->kind) {
                $ids[$activity->id] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            $this->repository->findForUpdate($id);
        }
    }

    private static function activity(CalendarEntry $entry): SourceActivity
    {
        $o = $entry->occurrence;

        return new SourceActivity($o->id, $entry->title, $o->startsAt, $o->endsAt, $entry->eventActive && 'SCHEDULED' === $o->status, (string) $o->scheduleRevision, $o->venueName);
    }
}
