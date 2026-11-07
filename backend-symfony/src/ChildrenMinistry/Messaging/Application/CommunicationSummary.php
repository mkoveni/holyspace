<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\ChildrenMinistry\Messaging\Domain\CommunicationStatus;
use App\ChildrenMinistry\Messaging\Domain\CommunicationType;
use App\Shared\Domain\CalendarDate;

/** Task metadata for the worklist: no wording. */
final readonly class CommunicationSummary
{
    public function __construct(public string $id, public string $childId, public string $guardianId, public string $programmeId, public ?string $sessionId, public CommunicationType $type, public CalendarDate $dueOn, public CommunicationStatus $status, public int $version)
    {
    }
}
