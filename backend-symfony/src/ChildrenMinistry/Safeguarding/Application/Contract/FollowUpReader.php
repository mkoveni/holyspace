<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

use App\ChildrenMinistry\Safeguarding\Application\FollowUpFacts;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

interface FollowUpReader
{
    /**
     * Active enrolments (at most 1,001), and when they are small enough the current pickup permissions and care review dates.
     *
     * @param list<string>|null $matchingChildIds null for every child; an empty list matches none
     */
    public function load(?string $programmeId, ?array $matchingChildIds, CalendarDate $on, DateTimeImmutable $at, bool $includeCare): FollowUpFacts;
}
