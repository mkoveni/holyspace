<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** An assignment with its gift's received date and whether that gift has since been reversed (Java `PledgeReader.Assignment`). */
final readonly class PledgeAssignmentEntry
{
    public function __construct(
        public PledgeAssignmentView $assignment,
        public CalendarDate $receivedOn,
        public bool $giftReversed,
    ) {
    }
}
