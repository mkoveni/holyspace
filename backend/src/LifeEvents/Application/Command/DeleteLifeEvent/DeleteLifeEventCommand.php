<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\DeleteLifeEvent;

use App\Shared\Application\CQRS\Command;

final readonly class DeleteLifeEventCommand implements Command
{
    public function __construct(public string $lifeEventId)
    {
    }
}
