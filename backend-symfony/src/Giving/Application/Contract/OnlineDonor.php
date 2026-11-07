<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** The donor a checkout may start for (Java `OnlineGivingLedger.Donor`). */
final readonly class OnlineDonor
{
    public function __construct(public string $name)
    {
    }
}
