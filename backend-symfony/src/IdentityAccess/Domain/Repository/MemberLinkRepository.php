<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\MemberLink;

interface MemberLinkRepository
{
    /** Loads the account's link holding a row lock until the surrounding transaction ends. */
    public function lock(string $accountId): ?MemberLink;

    /** Inserts or updates the link and appends an immutable history row carrying the evidence. */
    public function save(MemberLink $link, string $evidence): void;
}
