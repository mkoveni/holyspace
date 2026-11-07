<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/** Resolves a trusted caller after checking the live session and account status. */
interface AuthenticatedAccount
{
    public function requireAccount(): string;
}
