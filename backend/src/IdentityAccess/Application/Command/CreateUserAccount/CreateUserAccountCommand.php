<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateUserAccount;

final readonly class CreateUserAccountCommand
{
    public function __construct(public string $username, public string $plainPassword, public ?string $personId = null) {}
}
