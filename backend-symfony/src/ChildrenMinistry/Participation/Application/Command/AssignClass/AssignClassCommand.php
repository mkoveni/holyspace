<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\AssignClass;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> */
final readonly class AssignClassCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $enrolmentId,
        public string $classId,
        public CalendarDate $validFrom,
        public CalendarDate $validUntil,
    ) {
    }
}
