<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\LeaveCommunity;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class LeaveCommunityCommand implements Command
{
    public function __construct(public string $actorId, public string $membershipId, public int $expectedVersion, public ?string $reason)
    {
    }
}
