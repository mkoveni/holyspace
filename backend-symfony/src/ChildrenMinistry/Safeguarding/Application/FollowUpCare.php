<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use App\Shared\Domain\CalendarDate;

/** Only the review date of a care record; nothing from its payload. */
final readonly class FollowUpCare
{
    public function __construct(public string $childId, public CalendarDate $reviewOn)
    {
    }
}
