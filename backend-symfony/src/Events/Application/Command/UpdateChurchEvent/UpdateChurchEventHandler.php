<?php

declare(strict_types=1);

namespace App\Events\Application\Command\UpdateChurchEvent;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\EventRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdateChurchEventHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private EventRepository $events,
        private OccurrenceReader $occurrences,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateChurchEventCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');

        $note = EventRules::text($command->reason, 1000);
        $event = $this->events->findEventForUpdate($command->eventId) ?? throw EventException::missing('Event');
        EventRules::version($event->version, $command->expectedVersion);
        $now = $this->clock->now();
        if (!$command->active && $this->occurrences->hasFutureScheduled($event->id, $now)) {
            throw EventException::conflict('Cancel the remaining scheduled occurrences before archiving this event.');
        }
        $this->events->saveEvent($event->edit($command->title, $command->type, $command->description, $command->active, $command->expectedVersion));
        $this->journal->audit($command->actorId, 'event.updated', $event->id, $note, $now);
    }
}
