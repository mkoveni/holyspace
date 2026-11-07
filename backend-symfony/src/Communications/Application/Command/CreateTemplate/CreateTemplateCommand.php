<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\CreateTemplate;

use App\Shared\Application\Command;

/** @implements Command<string> the new template id */
final readonly class CreateTemplateCommand implements Command
{
    /** @param list<string> $variables */
    public function __construct(public string $actorId, public string $key, public string $name, public string $subject, public string $html, public array $variables)
    {
    }
}
