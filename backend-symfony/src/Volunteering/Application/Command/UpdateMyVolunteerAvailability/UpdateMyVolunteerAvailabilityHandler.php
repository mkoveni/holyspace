<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateMyVolunteerAvailability;

use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\OwnVolunteer;
use App\Volunteering\Application\VolunteerAvailability;
use App\Volunteering\Domain\VolunteerException;

/**
 * Java `MemberVolunteerActions.availability`: the caller replaces their own availability. The request names the
 * profile it was drafted for, so a draft never lands on a different linked profile with the same version.
 */
final readonly class UpdateMyVolunteerAvailabilityHandler implements CommandHandler
{
    public function __construct(
        private OwnVolunteer $own,
        private VolunteerAvailability $availability,
    ) {
    }

    public function handle(UpdateMyVolunteerAvailabilityCommand $command): void
    {
        [$accountId, $before] = $this->own->profile();
        if ($before->id !== $command->volunteerId) {
            throw VolunteerException::conflict('Your volunteer profile changed. Refresh before editing availability.');
        }
        $locked = $this->own->lock($accountId, $before->id);
        $this->availability->replace($accountId, $locked->id, $command->expectedVersion, $command->weekly, $command->exceptions);
    }
}
