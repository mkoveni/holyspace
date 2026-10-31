<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\RestoreLifeEvent;

use App\Shared\Application\CQRS\Command;

final readonly class RestoreLifeEventCommand implements Command
{
    public function __construct(public string $lifeEventId)
    {
    }
}
