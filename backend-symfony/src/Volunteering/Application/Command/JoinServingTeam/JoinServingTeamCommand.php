<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\JoinServingTeam;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class JoinServingTeamCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $teamId,
        public int $expectedVersion,
        public string $volunteerId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
    ) {
    }
}
