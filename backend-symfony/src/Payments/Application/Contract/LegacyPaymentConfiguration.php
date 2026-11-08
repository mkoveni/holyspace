<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ProviderCredentials;

/** The deployment (environment variable) credentials used before a provider is managed in the database. */
interface LegacyPaymentConfiguration
{
    public function credentials(string $provider, string $mode): ProviderCredentials;
}
