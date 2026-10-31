<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\AddPermissionToRole\AddPermissionToRoleCommand;
use App\IdentityAccess\Application\Command\CreateRole\CreateRoleCommand;
use App\IdentityAccess\Application\Command\RemovePermissionFromRole\RemovePermissionFromRoleCommand;
use App\IdentityAccess\Presentation\Http\DTO\RolePayload;
use App\Shared\Presentation\Http\DTO\CreatedResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('identity.manage')]
final class RolesController extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    #[Route('/api/roles', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        RolePayload $payload,
    ): JsonResponse {
        $envelope = $this->bus->dispatch(new CreateRoleCommand($payload->name, $payload->description));
        $id = $envelope->last(HandledStamp::class)?->getResult();

        return $this->json(new CreatedResponse($id?->toString()), Response::HTTP_CREATED);
    }

    #[Route('/api/roles/{roleId}/permissions/{permissionId}', methods: ['POST'])]
    public function addPermission(string $roleId, string $permissionId): JsonResponse
    {
        $this->bus->dispatch(new AddPermissionToRoleCommand($roleId, $permissionId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/roles/{roleId}/permissions/{permissionId}', methods: ['DELETE'])]
    public function removePermission(string $roleId, string $permissionId): JsonResponse
    {
        $this->bus->dispatch(new RemovePermissionFromRoleCommand($roleId, $permissionId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
