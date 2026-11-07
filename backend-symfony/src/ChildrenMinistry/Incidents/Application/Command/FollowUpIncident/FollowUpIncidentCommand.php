<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\FollowUpIncident;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<null> */
final readonly class FollowUpIncidentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $incidentId,
        public int $expectedVersion,
        public ?string $action,
        public ?string $ownerId,
        public ?CalendarDate $reviewOn,
        public ?string $guardianAuthorityId,
        public ?string $note,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['restricted' => true];
    }
}
