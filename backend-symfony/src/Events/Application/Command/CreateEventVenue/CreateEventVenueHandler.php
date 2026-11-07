<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CreateEventVenue;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\Repository\VenueRepository;
use App\Events\Domain\Venue;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateEventVenueHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VenueRepository $venues,
        private EventJournal $journal,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new venue id */
    public function handle(CreateEventVenueCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');

        $now = $this->clock->now();
        $venue = Venue::create($this->ids->uuid(), $command->name, $command->address, $command->exclusive);
        $this->venues->insert($venue);
        $this->journal->audit($command->actorId, 'venue.created', $venue->id, '', $now);

        return $venue->id;
    }
}
