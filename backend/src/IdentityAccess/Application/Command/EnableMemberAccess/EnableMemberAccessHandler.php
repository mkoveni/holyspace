<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\EnableMemberAccess;

use App\IdentityAccess\Application\Command\InvalidateMemberAccess\InvalidateMemberAccessCommand;
use App\IdentityAccess\Application\Command\InvalidateMemberAccess\InvalidateMemberAccessHandler;
use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\Repository\PermissionRepository;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EnableMemberAccessHandler
{
    public function __construct(
        private UserAccountRepository $users,
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private AccessControl $access,
        private ClockInterface $clock,
        private InvalidateMemberAccessHandler $invalidateMemberAccessHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(EnableMemberAccessCommand $message): array
    {
        $id = $message->id;
        $personId = $message->personId;
        $expectedPersonId = $message->expectedPersonId;
        if (!$this->access->isGranted('identity.manage')) {
            throw new AccessDenied('Account administration permission is required.');
        }
        Input::id($id);
        Input::id($personId);

        return $this->transactions->run(function () use ($id, $personId, $expectedPersonId) {
            $account = $this->users->findById(UserId::fromString($id)) ?? throw new OutOfBoundsException('Account not found.');
            if ('active' !== $account->status()->value) {
                throw new DomainException('Enable the account before configuring member access.');
            }
            if ($account->personId() !== $expectedPersonId) {
                throw new DomainException('The linked person changed. Reload the account before continuing.');
            }
            if (!$this->people->get($personId)) {
                throw new OutOfBoundsException('Person not found.');
            }
            $permission = $this->permissions->findByCode('member.portal') ?? throw new DomainException('Apply the member portal migrations first.');
            $role = $this->roles->findByName('Member portal');
            if (null !== $role) {
                if (1 !== count($role->permissionIds()) || !$role->hasPermission($permission->id())) {
                    throw new DomainException('The Member portal role must contain only member.portal. Review its permissions before continuing.');
                }
            } else {
                $role = Role::create(RoleId::generate(), 'Member portal', 'Own records only; authenticator enrolment required', $this->clock->now());
                $role->addPermission($permission->id(), $this->clock->now());
                $this->roles->save($role);
            }
            if (!$account->hasRole($role->id()) || $account->personId() !== $personId) {
                $account->assignRole($role->id(), $this->clock->now());
                $account->linkPerson($personId, $this->clock->now());
                $this->users->save($account);
                ($this->invalidateMemberAccessHandler)(new InvalidateMemberAccessCommand($id));
            }

            return ['id' => $id, 'personId' => $personId, 'message' => 'Member access enabled. Sign in again and complete authenticator setup.'];
        });
    }
}
