<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;

/** Non-charging provider checks, called outside any transaction. */
interface ProviderConnectionProbe
{
    public function check(ProviderConfiguration $configuration, ProviderCredentials $credentials): ProbeResult;
}
