<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\Username;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(private UserAccountRepository $users) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->findByUsername(Username::fromString($identifier));

        if ($user === null)
            throw new \Symfony\Component\Security\Core\Exception\UserNotFoundException(sprintf('User "%s" was not found.', $identifier));

        return new SymfonyUser($user);
    }
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SymfonyUser)
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }
    public function supportsClass(string $class): bool
    {
        return is_a($class, SymfonyUser::class, true);
    }
}
