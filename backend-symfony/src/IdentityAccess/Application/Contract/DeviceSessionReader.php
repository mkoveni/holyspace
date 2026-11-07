<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

interface DeviceSessionReader
{
    /** @return list<DeviceSession> unexpired sessions of the account, newest first */
    public function devices(string $accountId, DateTimeImmutable $now, int $limit, int $offset): array;
}
