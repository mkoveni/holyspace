<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserAccount;

use App\IdentityAccess\Application\DTO\UserAccountView;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;

final readonly class GetUserAccountHandler
{
    public function __construct(private UserAccountRepository $users) {}

    public function __invoke(GetUserAccountQuery $query): UserAccountView
    {
        $user = $this->users->findById(UserId::fromString($query->userId));
        if ($user === null) throw new UserAccountNotFound($query->userId);
        return new UserAccountView(
            $user->id()->toString(),
            $user->username()->value(),
            $user->personId(),
            $user->status()->value,
            array_map(static fn($id) => $id->toString(), $user->roleIds()),
            $user->createdAt()->format(DATE_ATOM),
            $user->updatedAt()->format(DATE_ATOM),
        );
    }
}
