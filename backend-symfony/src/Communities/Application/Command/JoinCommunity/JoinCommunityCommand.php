<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\JoinCommunity;

use App\Shared\Application\Command;

/** @implements Command<string> the membership id */
final readonly class JoinCommunityCommand implements Command
{
    public function __construct(public string $actorId, public ?string $requestId, public string $communityId, public string $personId)
    {
    }
}
