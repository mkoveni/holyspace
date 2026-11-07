<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessFacts;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

interface ReadinessReader
{
    /**
     * @param list<string> $childIds
     * @param ?string      $policyId the programme's current consent policy, if any
     */
    public function load(string $programmeId, ?string $policyId, array $childIds, CalendarDate $on, DateTimeImmutable $at): ReadinessFacts;
}
