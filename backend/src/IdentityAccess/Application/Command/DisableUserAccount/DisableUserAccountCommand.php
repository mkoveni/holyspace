<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\DisableUserAccount;
final readonly class DisableUserAccountCommand
{
    public function __construct(public string $userId) {}
}
