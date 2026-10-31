<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsAlias(AuthenticatedAccount::class)]
final readonly class SymfonyAuthenticatedAccount implements AuthenticatedAccount
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function user(): ?UserAccount
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof SymfonyUser ? $user->account() : null;
    }

    public function verified(): bool
    {
        $token = $this->tokens->getToken();

        return $token?->hasAttribute('mfa_verified') && true === $token->getAttribute('mfa_verified');
    }
}
