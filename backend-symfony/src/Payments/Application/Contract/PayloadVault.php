<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use SensitiveParameter;

/** AES-GCM vault keyed by the deployment-owned PAYMENTS_TOKEN_KEY; ciphertexts are shared with Java. */
interface PayloadVault
{
    public function encrypt(#[SensitiveParameter] string $token): string;

    public function decrypt(string $encrypted): string;
}
