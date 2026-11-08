<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\ConfigurationCheck;

/** The latest revision of one environment, redacted to "is set" flags (Java `ProviderConfigurationReader.Environment`). */
final readonly class ProviderEnvironment
{
    public function __construct(
        public string $mode,
        public ?string $revisionId,
        public string $publicUrl,
        public string $ipRanges,
        public bool $merchantIdSet,
        public bool $merchantKeySet,
        public bool $passphraseSet,
        public bool $yocoKeySet,
        public bool $webhookSecretSet,
        public bool $complete,
        public ?ConfigurationCheck $connection,
        public string $backendUrl,
    ) {
    }
}
