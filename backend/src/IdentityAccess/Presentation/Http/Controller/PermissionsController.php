<?php

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\CreatePermission\CreatePermissionCommand;
use App\Shared\Presentation\Http\AbstractRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PermissionsController extends AbstractRestController
{
    #[Route('/api/permissions', methods: ['POST'])]
    public function createPermission(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $id = $this->result(new CreatePermissionCommand(
            (string)($data['code'] ?? ''),
            (string)($data['description'] ?? '')
        ));

        return new JsonResponse(['id' => $id?->toString()], 201);
    }
}
