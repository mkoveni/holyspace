<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communities\Application\Command\ChangeCommunityCover\ChangeCommunityCoverCommand;
use App\Communities\Application\Query\GetCommunityCover\GetCommunityCoverHandler;
use App\Communities\Application\Query\GetCommunityCover\GetCommunityCoverQuery;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\CoverImageHttp;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Staff manage a group's or ministry's cover picture through the portal's image contract (`CoverImageHttp`). */
final readonly class CommunityCoverController
{
    public function __construct(private CommandBus $commands, private AuthenticatedAccount $account, private GetCommunityCoverHandler $covers)
    {
    }

    #[IsGranted('ministries.read|ministries.manage|ministries.lead')]
    #[Route('/api/media/community/{id}', methods: ['GET'])]
    public function show(#[PathUuid] string $id): JsonResponse
    {
        $cover = $this->covers->handle(new GetCommunityCoverQuery($id));

        return new JsonResponse(CoverImageHttp::metadata($cover->slot, $cover->image), 200, ['Cache-Control' => 'no-store']);
    }

    #[IsGranted('ministries.read|ministries.manage|ministries.lead')]
    #[Route('/api/media/community/{id}/content', methods: ['GET'])]
    public function content(#[PathUuid] string $id): Response
    {
        return CoverImageHttp::binary($this->covers->content(new GetCommunityCoverQuery($id)), 'no-store');
    }

    #[IsGranted('ministries.manage')]
    #[Route('/api/media/community/{id}', methods: ['POST'])]
    public function upload(#[PathUuid] string $id, Request $request): JsonResponse
    {
        $version = CoverImageHttp::expectedVersion($request);
        $this->commands->dispatch(new ChangeCommunityCoverCommand($this->account->requireAccount(), $id, CoverImageHttp::upload($request), $version));

        return $this->show($id);
    }

    #[IsGranted('ministries.manage')]
    #[Route('/api/media/community/{id}', methods: ['DELETE'])]
    public function remove(#[PathUuid] string $id, Request $request): JsonResponse
    {
        $this->commands->dispatch(new ChangeCommunityCoverCommand($this->account->requireAccount(), $id, null, CoverImageHttp::expectedVersion($request)));

        return $this->show($id);
    }
}
