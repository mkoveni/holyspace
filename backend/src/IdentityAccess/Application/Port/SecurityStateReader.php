<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

interface SecurityStateReader
{
    public function find(string $userId): ?array;
}
