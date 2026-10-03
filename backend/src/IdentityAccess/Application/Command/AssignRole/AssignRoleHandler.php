<?php
declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\AssignRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\IdentityAccess\Domain\Exception\RoleNotFound;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
#[AsMessageHandler]
final readonly class AssignRoleHandler {
 public function __construct(private UserAccountRepository $users, private RoleRepository $roles) {}
 public function __invoke(AssignRoleCommand $command): void { $user=$this->users->findById(UserId::fromString($command->userId)); if(!$user) throw new UserAccountNotFound($command->userId); $roleId=RoleId::fromString($command->roleId); if(!$this->roles->findById($roleId)) throw new RoleNotFound($command->roleId); $user->assignRole($roleId); $this->users->save($user); }
}
