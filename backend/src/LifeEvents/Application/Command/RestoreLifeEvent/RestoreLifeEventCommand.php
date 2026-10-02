<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\RestoreLifeEvent;

final readonly class RestoreLifeEventCommand
{
    public function __construct(public string $lifeEventId) {}
}
