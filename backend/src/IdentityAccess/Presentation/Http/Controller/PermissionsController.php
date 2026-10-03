<?php
declare(strict_types=1);
namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\CreatePermission\CreatePermissionCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PermissionsController
{
    public function __construct(private MessageBusInterface $bus) {}

    #[Route('/api/permissions', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $envelope = $this->bus->dispatch(new CreatePermissionCommand(
            (string) ($data['code'] ?? ''),
            (string) ($data['description'] ?? ''),
        ));
        $id = $envelope->last(HandledStamp::class)?->getResult();
        return new JsonResponse(['id' => $id?->toString()], Response::HTTP_CREATED);
    }
}
