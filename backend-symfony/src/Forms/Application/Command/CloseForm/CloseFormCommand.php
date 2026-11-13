<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\CloseForm;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CloseFormCommand implements Command
{
    public function __construct(public string $actorId, public string $formId, public int $expectedVersion)
    {
    }
}
