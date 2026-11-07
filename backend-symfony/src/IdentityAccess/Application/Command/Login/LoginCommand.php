<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\Login;

use App\IdentityAccess\Domain\IdentityException;
use SensitiveParameter;

final readonly class LoginCommand
{
    public string $deviceName;

    public function __construct(
        public string $username,
        #[SensitiveParameter]
        public string $password,
        ?string $deviceName = null,
    ) {
        $device = null === $deviceName || '' === trim($deviceName) ? 'Web browser' : trim($deviceName);
        if (mb_strlen($device) > 80) {
            throw IdentityException::invalid('Device name must be at most 80 characters.');
        }
        $this->deviceName = $device;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['command' => '[redacted]'];
    }
}
