<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/**
 * One currency's totals for a period (Java GivingReader.Total). Aggregate minor units are decimal strings, as in Java,
 * so no client has to trust a JSON number beyond 2^53. Net may be negative when reversals outweigh gifts.
 */
final readonly class GivingTotal
{
    public function __construct(
        public string $currency,
        public string $grossMinor,
        public string $reversedMinor,
        public string $netMinor,
    ) {
    }
}
