<?php
declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\DisableUserAccount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
#[AsMessageHandler]
final readonly class DisableUserAccountHandler { public function __construct(private UserAccountRepository $users) {} public function __invoke(DisableUserAccountCommand $command): void { $id=UserId::fromString($command->userId); $user=$this->users->findById($id); if(!$user) throw new UserAccountNotFound($command->userId); $user->disable(); $this->users->save($user); } }
