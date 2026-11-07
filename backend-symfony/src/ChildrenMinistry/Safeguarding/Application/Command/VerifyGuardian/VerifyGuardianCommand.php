<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\VerifyGuardian;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class VerifyGuardianCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $childId,
        public string $guardianId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?string $evidenceReference,
    ) {
    }
}
