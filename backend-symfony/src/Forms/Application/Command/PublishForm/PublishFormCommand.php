<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\PublishForm;

use App\Shared\Application\Command;

/** @implements Command<string|null> the public link token (shown once), or null for an internal form */
final readonly class PublishFormCommand implements Command
{
    public function __construct(public string $actorId, public string $formId, public int $expectedVersion)
    {
    }
}
