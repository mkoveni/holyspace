<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ApproveServingRole;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class ApproveServingRoleCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $teamId,
        public int $expectedVersion,
        public string $volunteerId,
        public string $roleId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
    ) {
    }
}
