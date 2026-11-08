<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Application\Readiness;
use DateTimeImmutable;

/** Java `MemberVolunteerViews.Duty`: one of my duties, without staff notes, evidence or other volunteers. */
final readonly class MemberDuty
{
    public function __construct(
        public string $id,
        public string $status,
        public string $shiftStatus,
        public string $team,
        public string $role,
        public ?string $activity,
        public ?string $location,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public Readiness $readiness,
        public ?MemberInvitation $invitation,
    ) {
    }
}
