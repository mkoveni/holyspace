<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\UpdateAccount;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateAccountCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $id,
        public string $email,
        public string $displayName,
        public ?int $expectedVersion = null,
    ) {
    }
}
