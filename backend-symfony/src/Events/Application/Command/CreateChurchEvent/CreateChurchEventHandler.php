<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CreateChurchEvent;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\JournalRequest;
use App\Events\Application\Fingerprint;
use App\Events\Application\JavaText;
use App\Events\Application\RequestReplay;
use App\Events\Application\SchedulePlanner;
use App\Events\Application\ScheduleReview;
use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\Repository\VenueRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateChurchEventHandler implements CommandHandler
{
    private const string KIND = 'CREATE_EVENT';

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

    /** @return string the event id (the original one when the request is repeated) */
    public function handle(CreateChurchEventCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');

        $event = EventDefinition::create($this->ids->uuid(), $command->title, $command->type, $command->description);
        $hash = Fingerprint::of($command->actorId, $command->title, $command->type, $command->description, JavaText::draft($command->schedule), $command->previewHash);
        $old = $this->replay->previous($command->requestId, self::KIND, $hash);
        if (null !== $old) {
            return $old;
        }
        $venue = $this->venues->findForUpdate($command->schedule->venueId) ?? throw EventException::missing('Venue');
        $old = $this->replay->previous($command->requestId, self::KIND, $hash);
        if (null !== $old) {
            return $old;
        }
        $preview = $this->review->preview($command->schedule);
        $this->review->confirm($preview, $command->previewHash);

        $now = $this->clock->now();
        $this->events->insertEvent($event);
        $series = $command->schedule->series($this->ids->uuid(), $event->id);
        $this->events->insertSeries($series);
        $this->planner->insertDates($command->actorId, $series, $preview, $venue, $now);
        $this->journal->record(new JournalRequest($command->requestId, self::KIND, $hash, $event->id, $command->actorId, $now));
        $this->journal->audit($command->actorId, 'event.created', $event->id, '', $now);

        return $event->id;
    }
}
