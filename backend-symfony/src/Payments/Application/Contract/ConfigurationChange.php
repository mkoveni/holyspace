<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use DateTimeImmutable;

final readonly class ConfigurationChange
{
    public function __construct(
        public string $provider,
        public string $mode,
        public int $version,
        public string $action,
        public string $note,
        public string $changedFields,
        public string $actorId,
        public string $actorName,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
