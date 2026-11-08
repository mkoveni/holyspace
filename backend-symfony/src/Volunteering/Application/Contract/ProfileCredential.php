<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `ServingViews.Credential`: a credential without its evidence note. */
final readonly class ProfileCredential
{
    public function __construct(
        public string $id,
        public string $typeId,
        public string $type,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }
}
