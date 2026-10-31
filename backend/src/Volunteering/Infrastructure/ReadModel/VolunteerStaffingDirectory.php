<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\ReadModel;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\ReadModel\StaffingDirectory;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use App\Volunteering\Domain\Service\VolunteerEligibility;
use DomainException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(StaffingDirectory::class)]
final readonly class VolunteerStaffingDirectory implements StaffingDirectory
{
    public function __construct(private VolunteeringRepository $records, private OccurrenceDirectory $occurrences)
    {
    }

    public function eligibleForOccurrence(string $personId, string $occurrenceId): bool
    {
        $occurrence = $this->occurrences->get($occurrenceId);

        if (!$occurrence || 'cancelled' === $occurrence['status']) {
            return false;
        }
        $shifts = array_column($this->records->all('shifts'), null, 'id');
        $roles = array_column($this->records->all('roles'), null, 'id');
        $profiles = array_filter($this->records->all('profiles'), static fn ($profile): bool => $profile->personId === $personId);
        foreach ($this->records->all('assignments') as $assignment) {
            if ($assignment->personId !== $personId || 'assigned' !== $assignment->status) {
                continue;
            }
            $shift = $shifts[$assignment->shiftId] ?? null;
            if (!$shift || $shift->occurrenceId !== $occurrenceId || 'open' !== $shift->status) {
                continue;
            }
            if (Input::instant($shift->startsAt) >= Input::instant($occurrence['endsAt']) || Input::instant($shift->endsAt) <= Input::instant($occurrence['startsAt'])) {
                continue;
            }
            $role = $roles[$shift->roleId] ?? null;

            if (!$role || !$role->requiresScreening) {
                continue;
            }
            foreach ($profiles as $profile) {
                try {
                    VolunteerEligibility::requireEligible($profile, $role, $shift);

                    return true;
                } catch (DomainException) {
                    continue;
                }
            }
        }

        return false;
    }
}
