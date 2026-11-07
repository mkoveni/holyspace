<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface SessionReader
{
    public function find(string $tokenHash): ?SessionRecord;

    public function findById(string $sessionId): ?SessionRecord;
}
