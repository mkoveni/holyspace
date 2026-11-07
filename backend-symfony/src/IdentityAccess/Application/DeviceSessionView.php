<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application;

use DateTimeImmutable;

final readonly class DeviceSessionView
{
    public function __construct(
        public string $id,
        public string $deviceName,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public bool $current,
    ) {
    }
}
