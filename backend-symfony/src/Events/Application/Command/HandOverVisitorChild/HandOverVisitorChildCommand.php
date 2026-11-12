<?php

declare(strict_types=1);

namespace App\Events\Application\Command\HandOverVisitorChild;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class HandOverVisitorChildCommand implements Command
{
    public function __construct(public string $actorId, public string $childId, public string $decision)
    {
    }
}
