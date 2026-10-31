<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\CancelLifeEvent;

use App\Shared\Application\CQRS\Command;

final readonly class CancelLifeEventCommand implements Command
{
    public function __construct(public string $lifeEventId)
    {
    }
}
