<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\DonorLink;

/** Verified donor links and their append-only history. */
interface DonorLinkRepository
{
    /** Locks the account's link row until the transaction ends; null when the account has none. */
    public function lock(string $accountId): ?DonorLink;

    /** Writes the link and one history row with the evidence. A person already linked to another account is an evidence conflict. */
    public function save(DonorLink $link, string $evidence): void;
}
