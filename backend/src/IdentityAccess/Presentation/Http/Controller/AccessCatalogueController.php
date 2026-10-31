<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Query\ListPermissions\ListPermissionsHandler;
use App\IdentityAccess\Application\Query\ListPermissions\ListPermissionsQuery;
use App\IdentityAccess\Application\Query\ListRoles\ListRolesHandler;
use App\IdentityAccess\Application\Query\ListRoles\ListRolesQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('identity.manage')]
final class AccessCatalogueController extends AbstractController
{
    #[Route('/api/permissions', methods: ['GET'])]
    public function permissions(ListPermissionsHandler $catalogue): JsonResponse
    {
        return $this->json($catalogue(new ListPermissionsQuery()));
    }

    #[Route('/api/roles', methods: ['GET'])]
    public function roles(ListRolesHandler $catalogue): JsonResponse
    {
        return $this->json($catalogue(new ListRolesQuery()));
    }
}
