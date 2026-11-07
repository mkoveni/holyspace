<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

interface LoginAttemptWriter
{
    public function lockLogin(string $identifierHash): void;

    public function throttled(string $identifierHash, DateTimeImmutable $now): bool;

    public function failed(string $identifierHash, DateTimeImmutable $now): void;

    public function clearFailures(string $identifierHash): void;
}
