<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Domain\Enum\UserStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof SymfonyUser) {
            return;
        }

        match ($user->account()->status()) {
            UserStatus::ACTIVE => null,
            UserStatus::DISABLED => throw new CustomUserMessageAccountStatusException('This account is disabled.'),
            UserStatus::LOCKED => throw new CustomUserMessageAccountStatusException('This account is locked.'),
        };
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // No additional post-authentication checks are required currently.
    }
}
