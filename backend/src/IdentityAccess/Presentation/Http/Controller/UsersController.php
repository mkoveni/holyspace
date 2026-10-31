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
use App\IdentityAccess\Presentation\Http\DTO\AccountPayload;
use App\IdentityAccess\Presentation\Http\DTO\PasswordPayload;
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
final class UsersController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly GetUserAccountHandler $getUser,
        private readonly GetUserPermissionsHandler $getPermissions,
    ) {
    }

    #[Route('/api/users', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        AccountPayload $payload,
    ): JsonResponse {
        $envelope = $this->bus->dispatch(new CreateUserAccountCommand($payload->username, $payload->password, $payload->personId));
        $id = $envelope->last(HandledStamp::class)?->getResult();

        return $this->json(new CreatedResponse($id?->toString()), Response::HTTP_CREATED);
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->json(($this->getUser)(new GetUserAccountQuery($id)));
    }

    #[Route('/api/users/{id}/permissions', methods: ['GET'])]
    public function permissions(string $id): JsonResponse
    {
        return $this->json(($this->getPermissions)(new GetUserPermissionsQuery($id)));
    }

    #[Route('/api/users/{id}/password', methods: ['PUT'])]
    public function changePassword(
        string $id,
        #[MapRequestPayload]
        PasswordPayload $payload,
    ): JsonResponse {
        $this->bus->dispatch(new ChangePasswordCommand($id, $payload->password));

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
