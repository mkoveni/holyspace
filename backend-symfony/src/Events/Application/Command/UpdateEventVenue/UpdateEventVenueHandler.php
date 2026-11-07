<?php

declare(strict_types=1);

namespace App\Events\Application\Command\UpdateEventVenue;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\VenueRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdateEventVenueHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VenueRepository $venues,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateEventVenueCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');

        $venue = $this->venues->findForUpdate($command->venueId) ?? throw EventException::missing('Venue');
        $edited = $venue->edit($command->name, $command->address, $command->active, $command->expectedVersion);
        $this->venues->save($edited);
        $this->journal->audit(
            $command->actorId,
            'venue.updated',
            $venue->id,
            'Existing occurrence location snapshots are retained.',
            $this->clock->now(),
        );
    }
}
