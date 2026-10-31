<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Service;

use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Domain\Model\ShiftRequirement;
use App\Volunteering\Domain\Model\VolunteerProfile;
use App\Volunteering\Domain\Model\VolunteerRole;
use DomainException;

final class VolunteerEligibility
{
    public static function requireEligible(VolunteerProfile $profile, VolunteerRole $role, ShiftRequirement $shift): void
    {
        if (!$profile->active || !in_array($role->id, $profile->approvedRoleIds, true)) {
            throw new DomainException('Volunteer is not approved for this role.');
        }
        $day = substr($shift->endsAt, 0, 10);
        if ($role->requiresScreening && (null === $profile->screeningValidUntil || $profile->screeningValidUntil < $day)) {
            throw new DomainException('Valid screening is required through the end of this shift.');
        }
        if ([] !== array_diff($role->requiredQualifications, $profile->qualifications) || ([] !== $role->requiredQualifications && (null === $profile->qualificationsValidUntil || $profile->qualificationsValidUntil < $day))) {
            throw new DomainException('Required qualifications are missing or expired.');
        }
        foreach ($profile->availability as $window) {
            if (Input::instant($window['startsAt']) <= Input::instant($shift->startsAt) && Input::instant($window['endsAt']) >= Input::instant($shift->endsAt)) {
                return;
            }
        }
        throw new DomainException('Volunteer is not available for the entire shift.');
    }
}
