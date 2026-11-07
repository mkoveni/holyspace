<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessSummary;
use App\Shared\Domain\CalendarDate;

final readonly class ProgrammeChild
{
    /** @param list<Placement> $classes */
    public function __construct(
        public string $enrolmentId,
        public string $childId,
        public string $name,
        public CalendarDate $validFrom,
        public CalendarDate $validUntil,
        public string $status,
        public array $classes,
        public ?ReadinessSummary $readiness,
    ) {
    }
}
