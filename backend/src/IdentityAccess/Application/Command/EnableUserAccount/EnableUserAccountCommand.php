<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\EnableUserAccount;
final readonly class EnableUserAccountCommand
{
    public function __construct(public string $userId) {}
}
