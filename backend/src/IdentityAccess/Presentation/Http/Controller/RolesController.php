<?php
declare(strict_types=1);
namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\AddPermissionToRole\AddPermissionToRoleCommand;
use App\IdentityAccess\Application\Command\CreateRole\CreateRoleCommand;
use App\IdentityAccess\Application\Command\RemovePermissionFromRole\RemovePermissionFromRoleCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RolesController
{
    public function __construct(private MessageBusInterface $bus) {}

    #[Route('/api/roles', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $envelope = $this->bus->dispatch(new CreateRoleCommand(
            (string) ($data['name'] ?? ''),
            (string) ($data['description'] ?? ''),
        ));
        $id = $envelope->last(HandledStamp::class)?->getResult();
        return new JsonResponse(['id' => $id?->toString()], Response::HTTP_CREATED);
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
