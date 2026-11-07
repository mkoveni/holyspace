<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** Encrypts device push tokens at rest. The key is deployment-owned. */
interface PushTokens
{
    public function available(): bool;

    public function encrypt(string $token): string;

    public function decrypt(string $encrypted): string;
}
