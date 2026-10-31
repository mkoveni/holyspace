<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\EnableUserAccount;

use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnableUserAccountHandler
{
    public function __construct(private UserAccountRepository $users)
    {
    }

    public function __invoke(EnableUserAccountCommand $command): void
    {
        $id = UserId::fromString($command->userId);
        $user = $this->users->findById($id);
        if (!$user) {
            throw new UserAccountNotFound($command->userId);
        }
        $user->enable();
        $this->users->save($user);
    }
}
