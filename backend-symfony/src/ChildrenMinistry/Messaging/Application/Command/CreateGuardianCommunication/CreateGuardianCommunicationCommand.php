<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\CreateGuardianCommunication;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> the communication id, the same one on every retry of the request */
final readonly class CreateGuardianCommunicationCommand implements Command
{
    public function __construct(public string $actorId, public string $requestId, public string $childId, public string $authorityId, public string $programmeId, public ?string $sessionId, public ?string $type, public CalendarDate $dueOn)
    {
    }
}
