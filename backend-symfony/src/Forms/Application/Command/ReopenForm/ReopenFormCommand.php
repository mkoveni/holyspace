<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReopenForm;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ReopenFormCommand implements Command
{
    public function __construct(public string $actorId, public string $formId, public int $expectedVersion)
    {
    }
}
