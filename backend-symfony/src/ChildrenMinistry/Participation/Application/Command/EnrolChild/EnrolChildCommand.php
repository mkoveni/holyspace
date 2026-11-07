<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\EnrolChild;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> */
final readonly class EnrolChildCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $programmeId,
        public string $childId,
        public CalendarDate $validFrom,
        public CalendarDate $validUntil,
    ) {
    }
}
