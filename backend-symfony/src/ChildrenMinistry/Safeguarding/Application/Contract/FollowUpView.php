<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class FollowUpView
{
    /** @param list<FollowUpIssue> $issues */
    public function __construct(public array $issues, public int $total, public FollowUpSummary $summary, public bool $careIncluded, public CalendarDate $through, public string $timeZone, public DateTimeImmutable $checkedAt)
    {
    }
}
