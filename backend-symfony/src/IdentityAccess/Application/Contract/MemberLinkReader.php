<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface MemberLinkReader
{
    public function memberLink(string $accountId): ?MemberLinkSummary;

    /** @return list<MemberLinkSummary> most recently verified first */
    public function memberLinks(int $limit, int $offset): array;
}
