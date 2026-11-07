<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/**
 * Originating contexts serialize staffing eligibility changes with attendance decisions. Implementations lock only
 * the activities of their own kind, de-duplicated and in ascending id order, and must run inside a transaction.
 */
interface ServingActivityLocks
{
    /** @param list<ServingActivity> $activities */
    public function lock(array $activities): void;
}
