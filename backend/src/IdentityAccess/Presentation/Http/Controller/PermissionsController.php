<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\CreatePermission\CreatePermissionCommand;
use App\IdentityAccess\Presentation\Http\DTO\PermissionPayload;
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
final class PermissionsController extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    #[Route('/api/permissions', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        PermissionPayload $payload,
    ): JsonResponse {
        $envelope = $this->bus->dispatch(new CreatePermissionCommand($payload->code, $payload->description));
        $id = $envelope->last(HandledStamp::class)?->getResult();

        return $this->json(new CreatedResponse($id?->toString()), Response::HTTP_CREATED);
    }
}
