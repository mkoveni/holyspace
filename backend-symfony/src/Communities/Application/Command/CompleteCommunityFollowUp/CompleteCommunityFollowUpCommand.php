<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CompleteCommunityFollowUp;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CompleteCommunityFollowUpCommand implements Command
{
    public function __construct(public string $actorId, public string $followUpId, public int $expectedVersion, public ?string $reason)
    {
    }
}
