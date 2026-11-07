<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateAccount;

use App\Shared\Application\Command;
use SensitiveParameter;

/** @implements Command<string> */
final readonly class CreateAccountCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $username,
        public string $email,
        public string $displayName,
        #[SensitiveParameter]
        public string $password,
    ) {
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['command' => '[redacted]'];
    }
}
