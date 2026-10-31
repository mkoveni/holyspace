<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

interface RecoveryCodeDelivery
{
    public function write(string $code, string $destination): void;
}
