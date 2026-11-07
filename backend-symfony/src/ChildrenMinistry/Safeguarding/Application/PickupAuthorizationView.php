<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use DateTimeImmutable;

final readonly class PickupAuthorizationView
{
    public function __construct(
        public string $id,
        public string $programmeId,
        public string $childId,
        public string $collectorId,
        public string $authorityId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public string $evidenceReference,
        public string $recordedBy,
        public ?DateTimeImmutable $revokedAt,
        public ?string $revocationReason,
        public int $version,
    ) {
    }
}
