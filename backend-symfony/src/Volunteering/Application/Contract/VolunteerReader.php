<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;

interface VolunteerReader
{
    public function volunteer(string $id): ?VolunteerView;

    /** @return list<CredentialTypeView> */
    public function credentialTypes(): array;

    /** @return list<CredentialView> */
    public function credentials(string $volunteerId): array;

    /** @return list<AvailabilityRule> rules of one availability revision */
    public function availability(string $volunteerId, int $revision): array;

    /** @return list<AvailabilityException> exceptions of one availability revision */
    public function exceptions(string $volunteerId, int $revision): array;
}
