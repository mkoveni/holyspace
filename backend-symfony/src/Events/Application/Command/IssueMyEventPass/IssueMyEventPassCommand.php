<?php

declare(strict_types=1);

namespace App\Events\Application\Command\IssueMyEventPass;

use App\Events\Application\IssuedPass;
use App\Shared\Application\Command;

/** @implements Command<IssuedPass> */
final readonly class IssueMyEventPassCommand implements Command
{
    public function __construct(public string $actorId, public string $occurrenceId)
    {
    }
}
