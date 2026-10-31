<?php

declare(strict_types=1);

namespace App\Volunteering\Application\ReadModel;

interface StaffingDirectory
{
    public function eligibleForOccurrence(string $personId, string $occurrenceId): bool;
}
