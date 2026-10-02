<?php
declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\ChangePassword;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\UserId;
#[AsMessageHandler]
final readonly class ChangePasswordHandler {
 public function __construct(private UserAccountRepository $users, private PasswordHasher $passwordHasher) {}
 public function __invoke(ChangePasswordCommand $command): void { if(trim($command->plainPassword)==='') throw new \InvalidArgumentException('Password cannot be empty.'); $user=$this->users->findById(UserId::fromString($command->userId)); if(!$user) throw new UserAccountNotFound($command->userId); $user->changePassword($this->passwordHasher->hash($command->plainPassword),new \DateTimeImmutable()); $this->users->save($user); }
}
