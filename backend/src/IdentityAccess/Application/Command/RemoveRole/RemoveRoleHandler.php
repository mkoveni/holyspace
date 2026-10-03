<?php
declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\RemoveRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
#[AsMessageHandler]
final readonly class RemoveRoleHandler { public function __construct(private UserAccountRepository $users) {} public function __invoke(RemoveRoleCommand $command): void { $user=$this->users->findById(UserId::fromString($command->userId)); if(!$user) throw new UserAccountNotFound($command->userId); $user->removeRole(RoleId::fromString($command->roleId)); $this->users->save($user); } }
