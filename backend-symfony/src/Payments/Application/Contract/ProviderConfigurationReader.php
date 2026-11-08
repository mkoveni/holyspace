<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ConfigurationCheck;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderSettings;

/** Restricted settings read side; never returns credentials, only whether each one is set. */
interface ProviderConfigurationReader
{
    /** @return list<ProviderSettingsView> both providers, ordered by provider */
    public function settings(): array;

    /** @return list<ConfigurationChange> newest first, at most 100 */
    public function history(): array;

    public function head(string $provider): ?ProviderSettings;

    public function revision(string $id): ?ProviderConfiguration;

    public function latest(string $provider, string $mode): ?ProviderConfiguration;

    public function latestCheck(string $revisionId): ?ConfigurationCheck;
}
