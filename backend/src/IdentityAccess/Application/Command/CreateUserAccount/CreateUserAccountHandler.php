<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateUserAccount;

use App\IdentityAccess\Domain\Exception\UserAccountAlreadyExists;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateUserAccountHandler
{
    public function __construct(private UserAccountRepository $users, private PasswordHasher $passwordHasher)
    {
    }

    public function __invoke(CreateUserAccountCommand $command): UserId
    {
        $username = Username::fromString($command->username);
        if ($this->users->existsByUsername($username)) {
            throw new UserAccountAlreadyExists($username->value());
        }
        if ('' === trim($command->plainPassword) || mb_strlen($command->plainPassword) < 12 || mb_strlen($command->plainPassword) > 4096) {
            throw new InvalidArgumentException('Passwords must contain 12 to 4096 characters.');
        }
        $id = UserId::generate();
        $this->users->save(UserAccount::create($id, $username, $this->passwordHasher->hash($command->plainPassword), $command->personId));

        return $id;
    }
}
