<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RecoverAccount;

use App\Shared\Application\CQRS\Command;

final readonly class RecoverAccountCommand implements Command
{
    public function __construct(
        public string $username,
        public string $code,
        public string $password,
    ) {
    }
}
