<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateUserAccount;

use App\Shared\Application\CQRS\Command;

final readonly class CreateUserAccountCommand implements Command
{
    public function __construct(
        public string $username,
        public string $plainPassword,
        public ?string $personId = null,
    ) {
    }
}
