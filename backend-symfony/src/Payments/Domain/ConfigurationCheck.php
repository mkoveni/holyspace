<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/** Non-charging connection-check evidence for one immutable configuration revision. */
final readonly class ConfigurationCheck
{
    public function __construct(
        public string $id,
        public string $revisionId,
        public bool $successful,
        public string $message,
        public string $checkedBy,
        public DateTimeImmutable $checkedAt,
    ) {
    }
}
