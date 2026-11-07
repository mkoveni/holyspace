<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\IssuePickupLabel;

use App\ChildrenMinistry\Sessions\Application\LabelIssue;
use App\Shared\Application\Command;

/** @implements Command<LabelIssue> */
final readonly class IssuePickupLabelCommand implements Command
{
    public function __construct(public string $actorId, public string $attendanceId, public int $expectedVersion, public ?string $replacementReason)
    {
    }
}
