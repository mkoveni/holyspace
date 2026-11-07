<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

final readonly class DeviceSession
{
    public function __construct(
        public string $id,
        public string $deviceName,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
