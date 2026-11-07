<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface AccountNames
{
    /**
     * @param list<string> $ids at most 100
     *
     * @return array<string, string> display names keyed by account id; unknown accounts are absent
     */
    public function names(array $ids): array;
}
