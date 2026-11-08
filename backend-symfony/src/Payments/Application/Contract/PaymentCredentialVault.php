<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ProviderCredentials;

interface PaymentCredentialVault
{
    /** Whether a valid 32-byte encryption key is configured. */
    public function ready(): bool;

    public function encrypt(ProviderCredentials $credentials): string;

    public function decrypt(string $encrypted): ProviderCredentials;
}
