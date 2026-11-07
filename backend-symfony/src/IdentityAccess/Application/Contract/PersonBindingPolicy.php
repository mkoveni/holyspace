<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/** Other contexts (such as giving) may veto linking an account to a person that contradicts verified identity they hold. */
interface PersonBindingPolicy
{
    public function permits(string $accountId, string $personId): bool;
}
