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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UsersController
{
    public function __construct(
        private MessageBusInterface $bus,
        private GetUserAccountHandler $getUser,
        private GetUserPermissionsHandler $getPermissions,
    ) {}

    #[Route('/api/users', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $envelope = $this->bus->dispatch(new CreateUserAccountCommand(
            (string) ($data['username'] ?? ''),
            (string) ($data['password'] ?? ''),
            isset($data['personId']) ? (string) $data['personId'] : null,
        ));
        $id = $envelope->last(HandledStamp::class)?->getResult();
        return new JsonResponse(['id' => $id?->toString()], Response::HTTP_CREATED);
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse(get_object_vars(($this->getUser)(new GetUserAccountQuery($id))));
    }

    #[Route('/api/users/{id}/permissions', methods: ['GET'])]
    public function permissions(string $id): JsonResponse
    {
        return new JsonResponse(($this->getPermissions)(new GetUserPermissionsQuery($id)));
    }

    #[Route('/api/users/{id}/password', methods: ['PUT'])]
    public function changePassword(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $this->bus->dispatch(new ChangePasswordCommand($id, (string) ($data['password'] ?? '')));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/users/{id}/disable', methods: ['POST'])]
    public function disable(string $id): JsonResponse
    {
        $this->bus->dispatch(new DisableUserAccountCommand($id));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/users/{id}/enable', methods: ['POST'])]
    public function enable(string $id): JsonResponse
    {
        $this->bus->dispatch(new EnableUserAccountCommand($id));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/users/{id}/roles/{roleId}', methods: ['POST'])]
    public function assignRole(string $id, string $roleId): JsonResponse
    {
        $this->bus->dispatch(new AssignRoleCommand($id, $roleId));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/users/{id}/roles/{roleId}', methods: ['DELETE'])]
    public function removeRole(string $id, string $roleId): JsonResponse
    {
        $this->bus->dispatch(new RemoveRoleCommand($id, $roleId));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
