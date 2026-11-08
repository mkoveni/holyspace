<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateVolunteerShift;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<null> */
final readonly class UpdateVolunteerShiftCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $shiftId,
        public int $expectedVersion,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public int $positions,
        public ?string $status,
    ) {
    }
}
