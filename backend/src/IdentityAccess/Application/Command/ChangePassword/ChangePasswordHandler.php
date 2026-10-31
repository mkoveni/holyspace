<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\ChangePassword;

use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\UserId;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangePasswordHandler
{
    public function __construct(private UserAccountRepository $users, private PasswordHasher $passwordHasher)
    {
    }

    public function __invoke(ChangePasswordCommand $command): void
    {
        if ('' === trim($command->plainPassword) || mb_strlen($command->plainPassword) < 12 || mb_strlen($command->plainPassword) > 4096) {
            throw new InvalidArgumentException('Passwords must contain 12 to 4096 characters.');
        }
        $user = $this->users->findById(UserId::fromString($command->userId));
        if (!$user) {
            throw new UserAccountNotFound($command->userId);
        }
        $user->changePassword($this->passwordHasher->hash($command->plainPassword));
        $this->users->save($user);
    }
}
