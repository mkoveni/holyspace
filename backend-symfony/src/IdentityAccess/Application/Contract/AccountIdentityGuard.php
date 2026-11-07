<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/** Serialize verified identity changes on the account root before link and People locks. */
interface AccountIdentityGuard
{
    public function lock(string $accountId): void;
}
