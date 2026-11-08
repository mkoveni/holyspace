<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RecordVolunteerCredential;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class RecordVolunteerCredentialCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $volunteerId,
        public int $expectedVersion,
        public ?string $typeId,
        public ?DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil,
        public ?string $evidence,
    ) {
    }
}
