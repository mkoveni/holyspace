<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CloseEventOccurrence\CloseEventOccurrenceCommand;
use App\Events\Application\Command\ReopenEventCloseout\ReopenEventCloseoutCommand;
use App\Events\Application\Contract\CloseoutHistoryEntry;
use App\Events\Application\Query\GetEventCloseoutHistory\GetEventCloseoutHistoryHandler;
use App\Events\Application\Query\GetEventCloseoutHistory\GetEventCloseoutHistoryQuery;
use App\Events\Application\Query\GetEventReadiness\GetEventReadinessHandler;
use App\Events\Application\Query\GetEventReadiness\GetEventReadinessQuery;
use App\Events\Presentation\Request\CloseEventOccurrenceRequest;
use App\Events\Presentation\Request\ReopenEventCloseoutRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Readiness review and closeout of an occurrence. Everything here needs events.manage. */
#[Route('/api/events/occurrences/{id}')]
#[IsGranted('events.manage')]
final readonly class EventCloseoutController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private GetEventReadinessHandler $readiness,
        private GetEventCloseoutHistoryHandler $history,
    ) {
    }

    #[Route('/readiness', methods: ['GET'])]
    public function readiness(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(EventsJson::readiness($this->readiness->handle(new GetEventReadinessQuery($id))));
    }

    #[Route('/closeout', methods: ['POST'])]
    public function close(#[PathUuid] string $id, #[MapRequestPayload] CloseEventOccurrenceRequest $request): Response
    {
        $this->commands->dispatch(new CloseEventOccurrenceCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->reviewHash,
            $request->note,
            $request->followUp,
            $request->attendanceReviewed,
            $request->followUpReviewed,
        ));

        return new Response(status: 204);
    }

    #[Route('/closeout/reopen', methods: ['POST'])]
    public function reopen(#[PathUuid] string $id, #[MapRequestPayload] ReopenEventCloseoutRequest $request): Response
    {
        $this->commands->dispatch(new ReopenEventCloseoutCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/closeout/history', methods: ['GET'])]
    public function history(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(
            static fn (CloseoutHistoryEntry $entry): array => EventsJson::closeoutEntry($entry),
            $this->history->handle(new GetEventCloseoutHistoryQuery($id, $limit, $offset)),
        ));
    }
}
