<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

/** Cross-account invariant: a church must retain an active account manager. */
final class AccountManagementPolicy
{
    private function __construct()
    {
    }

    public static function requireActiveManager(int $activeManagers): void
    {
        if ($activeManagers < 1) {
            throw IdentityException::conflict('At least one active account manager must remain.');
        }
    }
}
