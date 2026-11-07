<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\CancelSessionSeries\CancelSessionSeriesCommand;
use App\ChildrenMinistry\Sessions\Application\Command\CreateSessionSeries\CreateSessionSeriesCommand;
use App\ChildrenMinistry\Sessions\Application\Query\GetSessionSeries\GetSessionSeriesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetSessionSeries\GetSessionSeriesQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListSessionSeries\ListSessionSeriesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListSessionSeries\ListSessionSeriesQuery;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewSessionSeries\PreviewSessionSeriesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewSessionSeries\PreviewSessionSeriesQuery;
use App\ChildrenMinistry\Sessions\Presentation\Request\CancelSessionSeriesRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\CreateSessionSeriesRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\SessionSeriesDraftRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Recurring weekly sessions. Reads need children.read; previewing, creating and cancelling need children.manage. */
#[Route('/api/children/session-series')]
final readonly class SessionSeriesController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private PreviewSessionSeriesHandler $preview,
        private ListSessionSeriesHandler $list,
        private GetSessionSeriesHandler $get,
    ) {
    }

    #[IsGranted('children.manage')]
    #[Route('/preview', methods: ['POST'])]
    public function preview(#[MapRequestPayload] SessionSeriesDraftRequest $request): JsonResponse
    {
        return new JsonResponse(SessionSeriesJson::preview($this->preview->handle(new PreviewSessionSeriesQuery($request->toDraft()))));
    }

    #[IsGranted('children.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateSessionSeriesRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateSessionSeriesCommand(
            $this->account->requireAccount(),
            Json::uuid($request->requestId ?? throw new BadRequestException('Request id is required.')),
            ($request->draft ?? throw new BadRequestException('Draft is required.'))->toDraft(),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(SessionSeriesJson::page($this->list->handle(new ListSessionSeriesQuery($limit, $offset))));
    }

    #[IsGranted('children.read')]
    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(SessionSeriesJson::detail($this->get->handle(new GetSessionSeriesQuery($id))));
    }

    #[IsGranted('children.manage')]
    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(#[PathUuid] string $id, #[MapRequestPayload] CancelSessionSeriesRequest $request): Response
    {
        $this->commands->dispatch(new CancelSessionSeriesCommand($this->account->requireAccount(), $id, $request->expectedVersion, (string) $request->reason));

        return new Response(status: 204);
    }
}
