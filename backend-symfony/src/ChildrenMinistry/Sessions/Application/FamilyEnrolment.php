<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\Shared\Domain\CalendarDate;

/** A current or future programme enrolment of a child. */
final readonly class FamilyEnrolment
{
    public function __construct(public string $programmeId, public string $name, public CalendarDate $validFrom, public CalendarDate $validUntil)
    {
    }
}
