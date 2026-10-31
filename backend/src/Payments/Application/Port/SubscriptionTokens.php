<?php

declare(strict_types=1);

namespace App\Payments\Application\Port;

interface SubscriptionTokens
{
    public function encrypt(string $token): string;

    public function decrypt(string $encrypted): string;
}
