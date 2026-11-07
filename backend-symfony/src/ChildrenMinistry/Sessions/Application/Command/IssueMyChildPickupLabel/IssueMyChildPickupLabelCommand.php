<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\IssueMyChildPickupLabel;

use App\ChildrenMinistry\Sessions\Application\LabelIssue;
use App\Shared\Application\Command;

/** @implements Command<LabelIssue> */
final readonly class IssueMyChildPickupLabelCommand implements Command
{
    public function __construct(public string $actorId, public string $childId, public string $attendanceId, public int $expectedVersion, public bool $replacePrevious)
    {
    }

    public function __debugInfo(): array
    {
        return ['childId' => $this->childId, 'attendanceId' => $this->attendanceId];
    }
}
