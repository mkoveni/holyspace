<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\UpdateTemplate;

use App\Shared\Application\Command;

/** @implements Command<string> the template id */
final readonly class UpdateTemplateCommand implements Command
{
    /** @param list<string> $variables */
    public function __construct(public string $actorId, public string $id, public int $expectedVersion, public string $name, public string $subject, public string $html, public array $variables)
    {
    }
}
