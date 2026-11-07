<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

interface SessionWriter
{
    public function create(
        string $id,
        string $accountId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
        string $deviceName,
    ): void;

    public function revoke(string $sessionId, string $accountId): void;

    public function revokeAll(string $accountId): void;
}
