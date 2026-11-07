<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CreateEventSeries;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\JournalRequest;
use App\Events\Application\Fingerprint;
use App\Events\Application\JavaText;
use App\Events\Application\RequestReplay;
use App\Events\Application\SchedulePlanner;
use App\Events\Application\ScheduleReview;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\Repository\VenueRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateEventSeriesHandler implements CommandHandler
{
    private const string KIND = 'CREATE_SERIES';

    public function __construct(
        private PermissionChecker $authorization,
        private EventRepository $events,
        private VenueRepository $venues,
        private ScheduleReview $review,
        private SchedulePlanner $planner,
        private RequestReplay $replay,
        private EventJournal $journal,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the series id (the original one when the request is repeated) */
    public function handle(CreateEventSeriesCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');

        $hash = Fingerprint::of($command->actorId, $command->eventId, JavaText::draft($command->schedule), $command->previewHash);
        // Lock order: event, then venue (the series' occurrences are new rows).
        $event = $this->events->findEventForUpdate($command->eventId);
        if (null === $event || !$event->active) {
            throw EventException::missing('Active event');
        }
        $old = $this->replay->previous($command->requestId, self::KIND, $hash);
        if (null !== $old) {
            return $old;
        }
        EventRules::version($event->version, $command->expectedVersion);
        $venue = $this->venues->findForUpdate($command->schedule->venueId) ?? throw EventException::missing('Venue');
        $preview = $this->review->preview($command->schedule);
        $this->review->confirm($preview, $command->previewHash);

        $now = $this->clock->now();
        $series = $command->schedule->series($this->ids->uuid(), $event->id);
        $this->events->insertSeries($series);
        $this->planner->insertDates($command->actorId, $series, $preview, $venue, $now);
        $this->events->saveEvent($event->touch());
        $this->journal->record(new JournalRequest($command->requestId, self::KIND, $hash, $series->id, $command->actorId, $now));
        $this->journal->audit($command->actorId, 'series.created', $series->id, '', $now);

        return $series->id;
    }
}
