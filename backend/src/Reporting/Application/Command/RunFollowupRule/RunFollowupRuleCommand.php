<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\RunFollowupRule;

use App\Shared\Application\CQRS\Command;

final readonly class RunFollowupRuleCommand implements Command
{
    public function __construct(public string $id, public bool $force = false)
    {
    }
}
