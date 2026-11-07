<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RecordConsent;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class RecordConsentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $childId,
        public string $programmeId,
        public string $policyId,
        public string $authorityId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public DateTimeImmutable $acceptedAt,
        public ?string $evidenceReference,
    ) {
    }
}
