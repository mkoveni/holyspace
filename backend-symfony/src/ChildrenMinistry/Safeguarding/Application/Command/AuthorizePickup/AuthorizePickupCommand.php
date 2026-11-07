<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\AuthorizePickup;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class AuthorizePickupCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $programmeId,
        public string $childId,
        public string $collectorId,
        public string $authorityId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?string $evidenceReference,
    ) {
    }
}
