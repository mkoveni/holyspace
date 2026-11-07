<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use DateTimeImmutable;

final readonly class GuardianAuthorityView
{
    public function __construct(
        public string $id,
        public string $childId,
        public string $guardianId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public string $evidenceReference,
        public string $verifiedBy,
        public ?DateTimeImmutable $revokedAt,
        public ?string $revocationReason,
        public int $version,
    ) {
    }
}
