<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\UnpublishForm;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UnpublishFormCommand implements Command
{
    public function __construct(public string $actorId, public string $formId, public int $expectedVersion)
    {
    }
}
