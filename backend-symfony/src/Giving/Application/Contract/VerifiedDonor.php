<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** The donor the current account is verified as (Java `DonorIdentity.Donor`). */
final readonly class VerifiedDonor
{
    public function __construct(public string $id, public string $name)
    {
    }
}
