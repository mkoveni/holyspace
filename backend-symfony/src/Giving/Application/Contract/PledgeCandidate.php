<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** A gift that could be assigned to a pledge, with its unassigned amount on the pledge fund. Eligibility is checked again on assignment. */
final readonly class PledgeCandidate
{
    public function __construct(
        public string $giftId,
        public CalendarDate $receivedOn,
        public int $availableMinor,
        public ?string $reference,
        public string $method,
    ) {
    }
}
