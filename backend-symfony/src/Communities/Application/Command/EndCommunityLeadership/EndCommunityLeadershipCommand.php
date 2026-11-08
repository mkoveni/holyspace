<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\EndCommunityLeadership;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class EndCommunityLeadershipCommand implements Command
{
    public function __construct(public string $actorId, public string $leadershipId, public int $expectedVersion, public ?string $reason)
    {
    }
}
