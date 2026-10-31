<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\RunFollowupRuleManually;

use App\Shared\Application\CQRS\Command;

final readonly class RunFollowupRuleManuallyCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
