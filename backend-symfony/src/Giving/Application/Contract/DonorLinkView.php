<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use DateTimeImmutable;

/** A verified donor link as stored (Java `DonorLink` on the read side). */
final readonly class DonorLinkView
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
