<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessStepStatus;
use App\Shared\Domain\CalendarDate;

final readonly class FamilyProgramme
{
    /** @param list<ReadinessStepStatus> $readiness */
    public function __construct(public string $id, public string $name, public CalendarDate $validFrom, public CalendarDate $validUntil, public ?string $className, public array $readiness, public string $timeZone)
    {
    }
}
