<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ChangeEventCover;

use App\Events\Application\Contract\EventCoverReader;
use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\EventCoverRepository;
use App\Events\Domain\Repository\EventRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\CoverImage\CoverImages;
use Psr\Clock\ClockInterface;

/** Staff with `events.manage` change an event's cover. The event row lock serializes cover changes. */
final readonly class ChangeEventCoverHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private EventRepository $events,
        private EventCoverReader $reader,
        private EventCoverRepository $covers,
        private CoverImages $images,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ChangeEventCoverCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');
        $this->events->findEventForUpdate($command->eventId) ?? throw EventException::missing('Event');
        $current = $this->reader->cover($command->eventId);
        EventRules::version($current->version, $command->expectedVersion);
        $now = $this->clock->now();
        $this->covers->save($command->eventId, $this->images->next($current, $command->image, $now), $now);
        $this->images->discard($current);
        $this->journal->audit($command->actorId, 'event.cover_image.changed', $command->eventId, null === $command->image ? 'Cover image removed' : 'Cover image set', $now);
    }
}
