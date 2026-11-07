<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/**
 * Trusted admission capability. Callers must authorize their own entry point before use. Locks member identity before
 * People and occurrence locks; never grants guardian authority.
 */
interface MemberAdmissionGuard
{
    /** Locks and returns the account's active member link, or null when there is none. */
    public function lock(string $accountId): ?MemberAdmission;
}
