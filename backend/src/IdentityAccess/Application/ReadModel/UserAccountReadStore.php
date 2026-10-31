<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\ReadModel;

use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;

interface UserAccountReadStore
{
    public function findById(UserId $id): ?UserAccount;

    public function findByUsername(Username $username): ?UserAccount;

    public function existsByUsername(Username $username): bool;
}
