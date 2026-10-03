<?php

namespace App\IdentityAccess\Presentation\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use App\IdentityAccess\Application\Command\CreateRole\CreateRoleCommand;
use App\IdentityAccess\Application\Command\AddPermissionToRole\AddPermissionToRoleCommand;
use App\IdentityAccess\Application\Command\RemovePermissionFromRole\RemovePermissionFromRoleCommand;
use App\Shared\Presentation\Http\AbstractRestController;
use Symfony\Component\Messenger\MessageBusInterface;

class RolesController extends AbstractRestController
{
    public function __construct(MessageBusInterface $bus)
    {
        parent::__construct($bus);
    }

    #[Route('/api/roles', methods: ['POST'])]
    public function createRole(Request $r): JsonResponse
    {
        $d = $r->toArray();
        $id = $this->result(new CreateRoleCommand((string)($d['name'] ?? ''), (string)($d['description'] ?? '')));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/roles/{roleId}/permissions/{permissionId}', methods: ['POST'])]
    public function addPermission(string $roleId, string $permissionId): JsonResponse
    {
        $this->bus->dispatch(new AddPermissionToRoleCommand($roleId, $permissionId));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/roles/{roleId}/permissions/{permissionId}', methods: ['DELETE'])]
    public function removePermission(string $roleId, string $permissionId): JsonResponse
    {
        $this->bus->dispatch(new RemovePermissionFromRoleCommand($roleId, $permissionId));
        return new JsonResponse(null, 204);
    }
}
