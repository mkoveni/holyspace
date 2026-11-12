<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\VisitorCheckIn;
use DateTimeImmutable;

interface VisitorCheckInRepository
{
    public function insert(VisitorCheckIn $visit): void;

    public function exists(string $registrationId): bool;

    /** The review state, or null when the registration is not a visitor check-in. */
    public function reviewStatus(string $registrationId): ?string;

    /** Moves a review forward from one of the allowed states; false when the stored state was not allowed. */
    public function review(string $registrationId, string $status, string $actorId, DateTimeImmutable $at): bool;
}
