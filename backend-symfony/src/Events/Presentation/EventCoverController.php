<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\ChangeEventCover\ChangeEventCoverCommand;
use App\Events\Application\Query\GetEventCover\GetEventCoverHandler;
use App\Events\Application\Query\GetEventCover\GetEventCoverQuery;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\CoverImageHttp;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Staff manage an event's cover picture through the portal's image contract (`CoverImageHttp`). */
final readonly class EventCoverController
{
    public function __construct(private CommandBus $commands, private AuthenticatedAccount $account, private GetEventCoverHandler $covers)
    {
    }

    #[IsGranted('events.read')]
    #[Route('/api/media/event/{id}', methods: ['GET'])]
    public function show(#[PathUuid] string $id): JsonResponse
    {
        $cover = $this->covers->handle(new GetEventCoverQuery($id));

        return new JsonResponse(CoverImageHttp::metadata($cover->slot, $cover->image), 200, ['Cache-Control' => 'no-store']);
    }

    #[IsGranted('events.read')]
    #[Route('/api/media/event/{id}/content', methods: ['GET'])]
    public function content(#[PathUuid] string $id): Response
    {
        return CoverImageHttp::binary($this->covers->content(new GetEventCoverQuery($id)), 'no-store');
    }

    #[IsGranted('events.manage')]
    #[Route('/api/media/event/{id}', methods: ['POST'])]
    public function upload(#[PathUuid] string $id, Request $request): JsonResponse
    {
        $version = CoverImageHttp::expectedVersion($request);
        $this->commands->dispatch(new ChangeEventCoverCommand($this->account->requireAccount(), $id, CoverImageHttp::upload($request), $version));

        return $this->show($id);
    }

    #[IsGranted('events.manage')]
    #[Route('/api/media/event/{id}', methods: ['DELETE'])]
    public function remove(#[PathUuid] string $id, Request $request): JsonResponse
    {
        $this->commands->dispatch(new ChangeEventCoverCommand($this->account->requireAccount(), $id, null, CoverImageHttp::expectedVersion($request)));

        return $this->show($id);
    }
}
