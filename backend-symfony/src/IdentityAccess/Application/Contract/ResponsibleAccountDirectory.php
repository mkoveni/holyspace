<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface ResponsibleAccountDirectory
{
    /** True when the account is active and effectively holds the permission. */
    public function eligible(string $accountId, string $permission): bool;

    /** @return list<ResponsibleAccount> at most 25 active accounts holding the permission, matching the search */
    public function search(string $permission, string $search): array;
}
