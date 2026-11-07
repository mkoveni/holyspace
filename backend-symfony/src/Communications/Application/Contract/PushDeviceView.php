<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

/** One of the caller's device registrations, without its token, installation secret or session id. */
final readonly class PushDeviceView
{
    public function __construct(
        public string $id,
        public string $platform,
        public string $status,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $updatedAt,
        public bool $currentSession,
    ) {
    }
}
