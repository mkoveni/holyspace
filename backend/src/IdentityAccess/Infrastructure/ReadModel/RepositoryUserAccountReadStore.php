<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\ReadModel;

use App\IdentityAccess\Application\ReadModel\UserAccountReadStore;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserAccountReadStore::class)]
final readonly class RepositoryUserAccountReadStore implements UserAccountReadStore
{
    public function __construct(private UserAccountRepository $records)
    {
    }

    public function findById(UserId $id): ?UserAccount
    {
        return $this->records->findById($id);
    }

    public function findByUsername(Username $username): ?UserAccount
    {
        return $this->records->findByUsername($username);
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->records->existsByUsername($username);
    }
}
