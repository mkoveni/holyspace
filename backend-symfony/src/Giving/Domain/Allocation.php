<?php

declare(strict_types=1);

namespace App\Giving\Domain;

/** One fund's share of a gift, in the gift's minor units. Validated as part of the gift that holds it. */
final readonly class Allocation
{
    public function __construct(public string $fundId, public int $amountMinor)
    {
    }

    /** The Java record's toString, which feeds request hashes: Allocation[fundId=..., amountMinor=5000]. */
    public function javaString(): string
    {
        return sprintf('Allocation[fundId=%s, amountMinor=%d]', $this->fundId, $this->amountMinor);
    }
}
