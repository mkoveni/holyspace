<?php

declare(strict_types=1);

namespace App\Giving\Domain;

/** One frozen fund line of an acknowledgement: the fund name as it was when the acknowledgement was issued. */
final readonly class AcknowledgementLine
{
    public function __construct(
        public string $fundId,
        public string $fundName,
        public int $amountMinor,
    ) {
    }
}
