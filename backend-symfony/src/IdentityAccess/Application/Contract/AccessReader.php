<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface AccessReader
{
    /** @return list<RoleSummary> */
    public function roles(): array;

    /** @return list<PermissionSummary> */
    public function permissions(): array;

    /** @return list<string> */
    public function roleNames(string $accountId): array;

    /** @return list<string> granted permission codes, before implied permissions are added */
    public function permissionCodes(string $accountId): array;

    public function activeManagers(): int;
}
