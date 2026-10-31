<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\RecordStationArrival;

use App\Shared\Application\CQRS\Command;

final readonly class RecordStationArrivalCommand implements Command
{
    public function __construct(public string $sessionId, public string $childId)
    {
    }
}
