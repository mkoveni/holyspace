<?php
declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\CreateUserAccount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\IdentityAccess\Domain\Exception\UserAccountAlreadyExists;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
#[AsMessageHandler]
final readonly class CreateUserAccountHandler {
 public function __construct(private UserAccountRepository $users, private PasswordHasher $passwordHasher) {}
 public function __invoke(CreateUserAccountCommand $command): UserId { $username=Username::fromString($command->username); if($this->users->existsByUsername($username)) throw new UserAccountAlreadyExists($username->value()); if(trim($command->plainPassword)==='') throw new \InvalidArgumentException('Password cannot be empty.'); $id=UserId::generate(); $this->users->save(UserAccount::create($id,$username,$this->passwordHasher->hash($command->plainPassword),$command->personId)); return $id; }
}
