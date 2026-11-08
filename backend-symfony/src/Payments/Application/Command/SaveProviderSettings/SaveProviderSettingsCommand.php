<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\SaveProviderSettings;

use App\Payments\Domain\ProviderCredentials;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SaveProviderSettingsCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $provider,
        public string $mode,
        public int $expectedVersion,
        public bool $enabled,
        public bool $monthly,
        public ?string $publicUrl,
        public ?string $ipRanges,
        public ?ProviderCredentials $replacements,
        public string $reason,
        public ?string $backendUrl,
    ) {
    }

    /** @return array{provider: string, credentials: string} */
    public function __debugInfo(): array
    {
        return ['provider' => $this->provider, 'credentials' => 'redacted'];
    }
}
