<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\DeleteLifeEvent;

final readonly class DeleteLifeEventCommand
{
    public function __construct(public string $lifeEventId) {}
}
