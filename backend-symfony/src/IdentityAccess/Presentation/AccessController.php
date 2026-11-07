<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\Command\CreatePermission\CreatePermissionCommand;
use App\IdentityAccess\Application\Command\CreateRole\CreateRoleCommand;
use App\IdentityAccess\Application\Command\SetRolePermission\SetRolePermissionCommand;
use App\IdentityAccess\Application\Query\ListPermissions\ListPermissionsHandler;
use App\IdentityAccess\Application\Query\ListPermissions\ListPermissionsQuery;
use App\IdentityAccess\Application\Query\ListRoles\ListRolesHandler;
use App\IdentityAccess\Application\Query\ListRoles\ListRolesQuery;
use App\IdentityAccess\Presentation\Request\PermissionRequest;
use App\IdentityAccess\Presentation\Request\RoleRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Role and permission administration. Every operation requires identity.manage. */
#[IsGranted('identity.manage')]
final readonly class AccessController
{
    public function __construct(
        private CommandBus $commands,
        private Caller $caller,
        private ListRolesHandler $roles,
        private ListPermissionsHandler $permissions,
    ) {
    }

    #[Route('/api/roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        return new JsonResponse(array_map(AccountJson::role(...), $this->roles->handle(new ListRolesQuery())));
    }

    #[Route('/api/permissions', methods: ['GET'])]
    public function permissions(): JsonResponse
    {
        return new JsonResponse(array_map(AccountJson::permission(...), $this->permissions->handle(new ListPermissionsQuery())));
    }

    #[Route('/api/roles', methods: ['POST'])]
    public function createRole(#[MapRequestPayload] RoleRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateRoleCommand($this->caller->accountId(), $request->name, $request->description));

        return new JsonResponse(['id' => $id], 201);
    }

    /** A permission code is context.action, such as identity.read. */
    #[Route('/api/permissions', methods: ['POST'])]
    public function createPermission(#[MapRequestPayload] PermissionRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreatePermissionCommand($this->caller->accountId(), $request->code, $request->description));

        return new JsonResponse(['id' => $id], 201);
    }

    /** Grants the permission to every account holding the role. */
    #[Route('/api/roles/{roleId}/permissions/{permissionId}', methods: ['POST'])]
    public function assign(#[PathUuid] string $roleId, #[PathUuid] string $permissionId): Response
    {
        $this->commands->dispatch(new SetRolePermissionCommand($this->caller->accountId(), $roleId, $permissionId, true));

        return new Response(status: 204);
    }

    /** At least one active account manager must remain. */
    #[Route('/api/roles/{roleId}/permissions/{permissionId}', methods: ['DELETE'])]
    public function remove(#[PathUuid] string $roleId, #[PathUuid] string $permissionId): Response
    {
        $this->commands->dispatch(new SetRolePermissionCommand($this->caller->accountId(), $roleId, $permissionId, false));

        return new Response(status: 204);
    }
}
