<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface AccountReader
{
    public function find(string $id): ?AccountSummary;

    /** @param string $identifier a normalised username or email address */
    public function findByLogin(string $identifier): ?AccountSummary;

    /** @return list<AccountSummary> */
    public function search(string $search, int $limit): array;

    public function any(): bool;
}
