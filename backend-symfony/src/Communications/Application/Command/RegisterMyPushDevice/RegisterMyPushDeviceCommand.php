<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\RegisterMyPushDevice;

use App\Shared\Application\Command;
use SensitiveParameter;

/** @implements Command<null> */
final readonly class RegisterMyPushDeviceCommand implements Command
{
    public function __construct(
        public string $deviceId,
        #[SensitiveParameter] public string $installationSecret,
        #[SensitiveParameter] public string $token,
        public string $platform,
        public string $projectId,
    ) {
    }

    /** @return array<string, string> the secret and token are never dumped */
    public function __debugInfo(): array
    {
        return ['deviceId' => $this->deviceId, 'platform' => $this->platform];
    }
}
