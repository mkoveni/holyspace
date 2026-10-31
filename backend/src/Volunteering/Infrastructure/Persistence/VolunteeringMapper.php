<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;
use App\Volunteering\Domain\Model\ShiftRequirement;
use App\Volunteering\Domain\Model\VolunteerAssignment;
use App\Volunteering\Domain\Model\VolunteerInvitation;
use App\Volunteering\Domain\Model\VolunteerProfile;
use App\Volunteering\Domain\Model\VolunteerReminder;
use App\Volunteering\Domain\Model\VolunteerRole;

final class VolunteeringMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['invitations' => VolunteerInvitation::class, 'reminders' => VolunteerReminder::class, 'profiles' => VolunteerProfile::class, 'roles' => VolunteerRole::class, 'shifts' => ShiftRequirement::class, 'assignments' => VolunteerAssignment::class];
    }
}
