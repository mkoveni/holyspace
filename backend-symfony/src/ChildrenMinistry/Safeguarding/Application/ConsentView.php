<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use DateTimeImmutable;

final readonly class ConsentView
{
    public function __construct(
        public string $id,
        public string $programmeId,
        public string $policyId,
        public string $childId,
        public string $authorityId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public DateTimeImmutable $acceptedAt,
        public string $recordedBy,
        public string $evidenceReference,
        public ?DateTimeImmutable $revokedAt,
        public ?string $revocationReason,
        public int $version,
    ) {
    }
}
