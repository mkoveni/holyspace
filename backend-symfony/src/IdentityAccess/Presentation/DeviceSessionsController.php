<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\Command\RevokeMySession\RevokeMySessionCommand;
use App\IdentityAccess\Application\DeviceSessionView;
use App\IdentityAccess\Application\Query\ListMySessions\ListMySessionsHandler;
use App\IdentityAccess\Application\Query\ListMySessions\ListMySessionsQuery;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/** Any signed-in account manages its own device sessions; no staff permission is involved or granted. */
#[Route('/api/me/sessions')]
final readonly class DeviceSessionsController
{
    public function __construct(
        private Caller $caller,
        private CommandBus $commands,
        private ListMySessionsHandler $list,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListMySessionsQuery($limit, $offset));
        $response = new JsonResponse([
            'items' => array_map(static fn (DeviceSessionView $device): array => [
                'id' => $device->id,
                'deviceName' => $device->deviceName,
                'createdAt' => Json::instant($device->createdAt),
                'expiresAt' => Json::instant($device->expiresAt),
                'current' => $device->current,
            ], $page->items),
            'hasMore' => $page->hasMore,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/{sessionId}', methods: ['DELETE'])]
    public function revoke(#[PathUuid] string $sessionId): Response
    {
        $this->commands->dispatch(new RevokeMySessionCommand($this->caller->accountId(), $sessionId));

        return new Response(status: 204);
    }
}
