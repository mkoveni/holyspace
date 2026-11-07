<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\ChangePassword;

use App\Shared\Application\Command;
use SensitiveParameter;

/** @implements Command<null> */
final readonly class ChangePasswordCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $id,
        #[SensitiveParameter]
        public ?string $currentPassword,
        #[SensitiveParameter]
        public string $password,
        public bool $administratorReset,
        public ?int $expectedVersion = null,
    ) {
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['command' => '[redacted]'];
    }
}
