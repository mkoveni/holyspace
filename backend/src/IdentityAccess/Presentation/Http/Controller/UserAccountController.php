<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\AssignRole\AssignRoleCommand;
use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordCommand;
use App\IdentityAccess\Application\Command\CreateUserAccount\CreateUserAccountCommand;
use App\IdentityAccess\Application\Command\DisableUserAccount\DisableUserAccountCommand;
use App\IdentityAccess\Application\Command\EnableUserAccount\EnableUserAccountCommand;
use App\IdentityAccess\Application\Command\RemoveRole\RemoveRoleCommand;
use App\IdentityAccess\Application\Query\GetUserAccount\GetUserAccountHandler;
use App\IdentityAccess\Application\Query\GetUserAccount\GetUserAccountQuery;
use App\IdentityAccess\Application\Query\GetUserPermissions\GetUserPermissionsHandler;
use App\IdentityAccess\Application\Query\GetUserPermissions\GetUserPermissionsQuery;
use App\Shared\Presentation\Http\AbstractRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

abstract class IdentityAccessController extends AbstractRestController
{

    #[Route('/api/useraccounts', methods: ['POST'])]
    public function createUser(Request $r): JsonResponse
    {
        $d = $r->toArray();
        $id = $this->result(new CreateUserAccountCommand((string)($d['username'] ?? ''), (string)($d['password'] ?? ''), isset($d['personId']) ? (string)$d['personId'] : null));
        return new JsonResponse(['id' => $id?->toString()], Response::HTTP_CREATED);
    }

    #[Route('/api/useraccounts/{id}', methods: ['GET'])]
    public function getUser(string $id, GetUserAccountHandler $getUser): JsonResponse
    {
        $v = $getUser(new GetUserAccountQuery($id));
        return new JsonResponse(get_object_vars($v));
    }

    #[Route('/api/useraccounts/{id}/permissions', methods: ['GET'])]
    public function permissions(string $id, GetUserPermissionsHandler $getPermissions): JsonResponse
    {
        return new JsonResponse($getPermissions(new GetUserPermissionsQuery($id)));
    }

    #[Route('/api/useraccounts/{id}/password', methods: ['PUT'])]
    public function password(string $id, Request $r): JsonResponse
    {
        $d = $r->toArray();
        $this->bus->dispatch(new ChangePasswordCommand($id, (string)($d['password'] ?? '')));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/useraccounts/{id}/disable', methods: ['POST'])]
    public function disable(string $id): JsonResponse
    {
        $this->bus->dispatch(new DisableUserAccountCommand($id));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/useraccounts/{id}/enable', methods: ['POST'])]
    public function enable(string $id): JsonResponse
    {
        $this->bus->dispatch(new EnableUserAccountCommand($id));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/useraccounts/{id}/roles/{roleId}', methods: ['POST'])]
    public function assignRole(string $id, string $roleId): JsonResponse
    {
        $this->bus->dispatch(new AssignRoleCommand($id, $roleId));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/useraccounts/{id}/roles/{roleId}', methods: ['DELETE'])]
    public function removeRole(string $id, string $roleId): JsonResponse
    {
        $this->bus->dispatch(new RemoveRoleCommand($id, $roleId));
        return new JsonResponse(null, 204);
    }
}
