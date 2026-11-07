<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CreateSession;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> the new session id */
final readonly class CreateSessionCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $classId,
        public string $roomId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $seatLimit,
    ) {
    }
}
