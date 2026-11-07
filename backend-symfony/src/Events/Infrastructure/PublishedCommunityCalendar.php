<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Events\Application\Command\CreateChurchEvent\CreateChurchEventCommand;
use App\Events\Application\Command\CreateChurchEvent\CreateChurchEventHandler;
use App\Events\Application\Contract\CalendarEntry;
use App\Events\Application\Contract\CalendarFailure;
use App\Events\Application\Contract\CalendarOccurrence;
use App\Events\Application\Contract\CalendarPreview;
use App\Events\Application\Contract\CommunityCalendar;
use App\Events\Application\Contract\CommunitySchedule;
use App\Events\Application\Contract\CommunityScheduleReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Application\ScheduleDraft;
use App\Events\Application\ScheduleReview;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;

/** Events' calendar as published to Communities: previews and creates meeting schedules and reads or locks their dates. */
final readonly class PublishedCommunityCalendar implements CommunityCalendar, CommunityScheduleReader
{
    private const int HORIZON_YEARS = 3;
    private const int MAX_DATES = 105;

    public function __construct(
        private ScheduleReview $reviews,
        private CreateChurchEventHandler $creation,
        private OccurrenceReader $reader,
        private OccurrenceRepository $repository,
        private PermissionChecker $authorization,
    ) {
    }

    public function preview(CommunitySchedule $schedule): CalendarPreview
    {
        return $this->translate(function () use ($schedule): CalendarPreview {
            $this->authorization->requirePermission('events.manage');
            $preview = $this->reviews->preview($this->draft($schedule));

            return new CalendarPreview($preview->hash, array_map(
                static fn ($window): CalendarOccurrence => new CalendarOccurrence(null, $window->startsAt, $window->endsAt, $schedule->timeZone, $preview->venue->name, 'SCHEDULED', 0),
                $preview->dates,
            ));
        });
    }

    public function create(string $actorId, string $requestId, string $name, CommunitySchedule $schedule, ?string $hash): array
    {
        return $this->translate(function () use ($actorId, $requestId, $name, $schedule, $hash): array {
            $this->authorization->requireActorPermission($actorId, 'events.manage');
            $event = $this->creation->handle(new CreateChurchEventCommand($actorId, $requestId, $name, 'MEETING', 'Group gathering', $this->draft($schedule), $hash));
            $zone = EventRules::zone($schedule->timeZone);
            $from = (new DateTimeImmutable($schedule->firstDate->iso().' 00:00:00', $zone))->modify('-86400 seconds');
            $until = new DateTimeImmutable($schedule->firstDate->plusYears(self::HORIZON_YEARS)->iso().' 00:00:00', $zone);

            return array_map(
                static fn (CalendarEntry $entry): CalendarOccurrence => self::date($entry->occurrence),
                $this->reader->calendar($event, '', $from, $until, null, self::MAX_DATES, 0),
            );
        });
    }

    public function get(string $occurrenceId): ?CalendarOccurrence
    {
        $view = $this->reader->find($occurrenceId);

        return null === $view ? null : self::date($view);
    }

    public function dates(array $occurrenceIds): array
    {
        $dates = [];
        foreach ($occurrenceIds as $id) {
            $view = $this->reader->find($id);
            if (null !== $view) {
                $dates[] = self::date($view);
            }
        }

        return $dates;
    }

    public function lock(string $occurrenceId): ?CalendarOccurrence
    {
        $occurrence = $this->repository->findForUpdate($occurrenceId);

        return null === $occurrence ? null : self::date(OccurrenceView::of($occurrence));
    }

    private function draft(CommunitySchedule $s): ScheduleDraft
    {
        return new ScheduleDraft(strtolower($s->venueId), $s->timeZone, $s->frequency, $s->interval, $s->count, $s->firstDate, $s->startsAt, $s->endsAt, $s->endDayOffset);
    }

    private static function date(OccurrenceView $o): CalendarOccurrence
    {
        return new CalendarOccurrence($o->id, $o->startsAt, $o->endsAt, $o->timeZone, $o->venueName, $o->status, $o->scheduleRevision);
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     *
     * @throws CalendarFailure
     */
    private function translate(callable $action): mixed
    {
        try {
            return $action();
        } catch (EventException $e) {
            throw new CalendarFailure(match ($e->kind()) {
                ErrorKind::Invalid => 400,
                ErrorKind::NotFound => 404,
                default => 409,
            }, $e->getMessage());
        }
    }
}
