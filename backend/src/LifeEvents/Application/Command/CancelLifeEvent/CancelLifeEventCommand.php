<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\CancelLifeEvent;

final readonly class CancelLifeEventCommand
{
    public function __construct(public string $lifeEventId) {}
}
