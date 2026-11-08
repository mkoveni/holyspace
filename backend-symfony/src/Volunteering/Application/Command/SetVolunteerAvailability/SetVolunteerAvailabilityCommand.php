<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SetVolunteerAvailability;

use App\Shared\Application\Command;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;

/** @implements Command<null> */
final readonly class SetVolunteerAvailabilityCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $volunteerId,
        public int $expectedVersion,
        /** @var list<AvailabilityRule>|null */
        public ?array $weekly,
        /** @var list<AvailabilityException>|null */
        public ?array $exceptions,
    ) {
    }
}
