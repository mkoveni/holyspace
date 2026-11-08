<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;

/** Non-charging provider checks, called outside any transaction. */
interface ProviderConnectionProbe
{
    /** 400 unless every comma-separated network is an explicit address or a restricted CIDR range (at most 30). */
    public function validateNetworks(string $ipRanges): void;

    public function check(ProviderConfiguration $configuration, ProviderCredentials $credentials): ProbeResult;
}
