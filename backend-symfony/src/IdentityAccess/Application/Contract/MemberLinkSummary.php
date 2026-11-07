<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

/** Read model of a verified account-to-person link. */
final readonly class MemberLinkSummary
{
    public function __construct(
        public string $accountId,
        public string $personId,
        public bool $active,
        public int $version,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt,
    ) {
    }
}
