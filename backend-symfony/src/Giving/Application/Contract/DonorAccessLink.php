<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** A donor link with display names, for the donor-access administration view (Java `ListDonorAccessHandler.Link`). */
final readonly class DonorAccessLink
{
    public function __construct(
        public string $accountId,
        public string $accountName,
        public string $personId,
        public string $personName,
        public bool $active,
        public int $version,
    ) {
    }
}
