<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\ArchiveTemplate;

use App\Shared\Application\Command;

/** @implements Command<string> the template id */
final readonly class ArchiveTemplateCommand implements Command
{
    public function __construct(public string $actorId, public string $id, public int $expectedVersion)
    {
    }
}
