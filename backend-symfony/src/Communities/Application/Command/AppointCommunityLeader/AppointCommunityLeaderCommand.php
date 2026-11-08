<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\AppointCommunityLeader;

use App\Shared\Application\Command;

/** @implements Command<string> the appointment id */
final readonly class AppointCommunityLeaderCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $requestId,
        public string $membershipId,
        public int $expectedVersion,
        public string $role,
        public ?string $accountId,
        public ?string $reason,
    ) {
    }
}
