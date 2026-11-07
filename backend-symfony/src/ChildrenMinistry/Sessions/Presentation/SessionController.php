<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\CreateSession\CreateSessionCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStatus\SetSessionStatusCommand;
use App\ChildrenMinistry\Sessions\Application\Query\GetSession\GetSessionHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetSession\GetSessionQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetSessionCloseout\GetSessionCloseoutHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetSessionCloseout\GetSessionCloseoutQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListSessions\ListSessionsHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListSessions\ListSessionsQuery;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Presentation\Request\CreateSessionRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\SetSessionStatusRequest;
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

/** Scheduling sessions. Reads need children.read, writes children.manage. */
#[Route('/api/children/sessions')]
final readonly class SessionController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListSessionsHandler $list,
        private GetSessionHandler $get,
        private GetSessionCloseoutHandler $closeout,
    ) {
    }

    #[IsGranted('children.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateSessionRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateSessionCommand(
            $this->account->requireAccount(),
            Json::uuid($request->classId ?? throw new BadRequestException('Class is required.')),
            Json::uuid($request->roomId ?? throw new BadRequestException('Room is required.')),
            Json::parseInstant($request->startsAt ?? throw new BadRequestException('Start is required.')),
            Json::parseInstant($request->endsAt ?? throw new BadRequestException('End is required.')),
            $request->seatLimit,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.manage')]
    #[Route('/status', methods: ['PUT'])]
    public function setStatus(#[MapRequestPayload] SetSessionStatusRequest $request): Response
    {
        $this->commands->dispatch(new SetSessionStatusCommand(
            $this->account->requireAccount(),
            Json::uuid($request->sessionId ?? throw new BadRequestException('Session is required.')),
            $request->expectedVersion,
            SessionStatus::from($request->status ?? throw new BadRequestException('Status is required.')),
            $request->reason,
            true === $request->acknowledgeIncidents,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('children.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $locationId = null,
        #[MapQueryParameter] ?string $from = null,
        #[MapQueryParameter] ?string $until = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $views = $this->list->handle(new ListSessionsQuery(
            null === $locationId ? null : Json::uuid($locationId),
            null === $from ? null : Json::parseInstant($from),
            null === $until ? null : Json::parseInstant($until),
            $limit,
            $offset,
        ));

        return new JsonResponse(SessionJson::views($views));
    }

    #[IsGranted('children.manage')]
    #[Route('/{sessionId}/closeout', methods: ['GET'])]
    public function closeout(#[PathUuid] string $sessionId): JsonResponse
    {
        return new JsonResponse(AttendanceJson::closeout($this->closeout->handle(new GetSessionCloseoutQuery($this->account->requireAccount(), $sessionId))));
    }

    #[IsGranted('children.read')]
    #[Route('/{sessionId}', methods: ['GET'])]
    public function get(#[PathUuid] string $sessionId): JsonResponse
    {
        return new JsonResponse(SessionJson::view($this->get->handle(new GetSessionQuery($sessionId))));
    }
}
