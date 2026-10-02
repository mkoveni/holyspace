<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Domain\Model\UserAccount;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SymfonyUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(private readonly UserAccount $account) {}
    public function getUserIdentifier(): string
    {
        return $this->account->username()->value();
    }
    public function getRoles(): array
    {
        return array_map(fn($role) => 'ROLE_' . $role->toString(), $this->account->roleIds()) ?: ['ROLE_USER'];
    }
    public function getPassword(): ?string
    {
        return $this->account->passwordHash()->value();
    }
    public function eraseCredentials(): void {}
    public function account(): UserAccount
    {
        return $this->account;
    }
}
