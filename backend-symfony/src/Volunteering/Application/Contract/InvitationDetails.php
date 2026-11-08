<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Application\Readiness;
use DateTimeImmutable;

/**
 * Java `ServingViews.InvitationView`. The effective status says WITHDRAWN or EXPIRED for a pending invitation whose duty
 * changed or whose time ran out, without writing anything.
 */
final readonly class InvitationDetails
{
    public function __construct(
        public InvitationRecord $invitation,
        public string $effectiveStatus,
        public string $team,
        public string $role,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public Readiness $readiness,
    ) {
    }
}
