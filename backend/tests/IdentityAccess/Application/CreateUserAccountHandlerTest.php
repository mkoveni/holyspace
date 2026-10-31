<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Application\Command\CreateUserAccount\CreateUserAccountCommand;
use App\IdentityAccess\Application\Command\CreateUserAccount\CreateUserAccountHandler;
use App\IdentityAccess\Domain\Exception\UserAccountAlreadyExists;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateUserAccountHandlerTest extends TestCase
{
    public function testCreatesAccountAndHashesPassword(): void
    {
        $repo = $this->createMock(UserAccountRepository::class);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::once())->method('hash')->with('a-long-secret-passphrase')->willReturn(PasswordHash::fromString('hashed'));
        $repo->expects(self::once())->method('existsByUsername')->with(self::callback(fn (Username $u): bool => 'admin' === $u->value()))->willReturn(false);
        $repo->expects(self::once())->method('save')->with(self::callback(fn (UserAccount $user): bool => $user->createdAt() <= $user->updatedAt() && 'hashed' === $user->passwordHash()->value()));
        $id = (new CreateUserAccountHandler($repo, $hasher))(new CreateUserAccountCommand('ADMIN', 'a-long-secret-passphrase', 'person-1'));
        self::assertInstanceOf(UserId::class, $id);
    }

    public function testShortPasswordsAreRejectedBeforeHashingOrSaving(): void
    {
        $repo = $this->createMock(UserAccountRepository::class);
        $repo->expects(self::once())->method('existsByUsername')->willReturn(false);
        $repo->expects(self::never())->method('save');
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $this->expectException(InvalidArgumentException::class);
        (new CreateUserAccountHandler($repo, $hasher))(new CreateUserAccountCommand('manager', 'short'));
    }

    public function testRejectsDuplicateUsername(): void
    {
        $repo = $this->createMock(UserAccountRepository::class);
        $hasher = $this->createMock(PasswordHasher::class);
        $repo->expects(self::once())->method('existsByUsername')->willReturn(true);
        $repo->expects(self::never())->method('save');
        $hasher->expects(self::never())->method('hash');
        $this->expectException(UserAccountAlreadyExists::class);
        (new CreateUserAccountHandler($repo, $hasher))(new CreateUserAccountCommand('admin', 'a-long-secret-passphrase'));
    }
}
