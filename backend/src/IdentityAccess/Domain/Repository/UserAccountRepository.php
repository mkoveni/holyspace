<?php

declare(strict_types=1);
namespace App\IdentityAccess\Domain\Repository;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
interface UserAccountRepository
{
    public function save(UserAccount $user): void;
    public function findById(UserId $id): ?UserAccount;
    public function findByUsername(Username $username): ?UserAccount;
    public function existsByUsername(Username $username): bool;
}
