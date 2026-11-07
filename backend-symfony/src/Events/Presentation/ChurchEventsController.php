<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CreateChurchEvent\CreateChurchEventCommand;
use App\Events\Application\Command\CreateEventSeries\CreateEventSeriesCommand;
use App\Events\Application\Command\UpdateChurchEvent\UpdateChurchEventCommand;
use App\Events\Application\Contract\EventView;
use App\Events\Application\Contract\SeriesView;
use App\Events\Application\Query\GetChurchEvent\GetChurchEventHandler;
use App\Events\Application\Query\GetChurchEvent\GetChurchEventQuery;
use App\Events\Application\Query\ListChurchEvents\ListChurchEventsHandler;
use App\Events\Application\Query\ListChurchEvents\ListChurchEventsQuery;
use App\Events\Application\Query\PreviewEventSchedule\PreviewEventScheduleHandler;
use App\Events\Application\Query\PreviewEventSchedule\PreviewEventScheduleQuery;
use App\Events\Presentation\Request\CreateChurchEventRequest;
use App\Events\Presentation\Request\CreateEventSeriesRequest;
use App\Events\Presentation\Request\PreviewEventScheduleRequest;
use App\Events\Presentation\Request\UpdateChurchEventRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Church events, their recurring series and the schedule preview. Reads need events.read, writes events.manage. */
#[Route('/api/events')]
final readonly class ChurchEventsController
{
    /** Any single segment except the literal sub-resources, so a malformed id is a 400 from PathUuid and not a route clash. */
    private const string ID = '(?!venues$|calendar$|attendance-report|deliveries$)[^/]+';

    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private PreviewEventScheduleHandler $preview,
        private ListChurchEventsHandler $list,
        private GetChurchEventHandler $get,
    ) {
    }

    #[IsGranted('events.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateChurchEventRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateChurchEventCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $request->title,
            $request->type,
            $request->description,
            ($request->schedule ?? throw new BadRequestException('A schedule is required.'))->draft(),
            $request->previewHash,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('events.manage')]
    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => self::ID])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] UpdateChurchEventRequest $request): Response
    {
        $this->commands->dispatch(new UpdateChurchEventCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->title,
            $request->type,
            $request->description,
            $request->active,
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('events.manage')]
    #[Route('/{id}/series', methods: ['POST'], requirements: ['id' => self::ID])]
    public function createSeries(#[PathUuid] string $id, #[MapRequestPayload] CreateEventSeriesRequest $request): JsonResponse
    {
        $seriesId = $this->commands->dispatch(new CreateEventSeriesCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            $request->expectedVersion,
            ($request->schedule ?? throw new BadRequestException('A schedule is required.'))->draft(),
            $request->previewHash,
        ));

        return new JsonResponse(['id' => $seriesId], 201);
    }

    #[IsGranted('events.read')]
    #[Route('/schedule/preview', methods: ['POST'])]
    public function preview(#[MapRequestPayload] PreviewEventScheduleRequest $request): JsonResponse
    {
        $draft = ($request->draft ?? throw new BadRequestException('A draft is required.'))->draft();

        return new JsonResponse(EventsJson::preview($this->preview->handle(new PreviewEventScheduleQuery($draft))));
    }

    #[IsGranted('events.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] bool $includeArchived = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListChurchEventsQuery($search, $includeArchived, $limit, $offset));

        return new JsonResponse([
            'events' => array_map(static fn (EventView $event): array => EventsJson::event($event), $page->events),
            'total' => $page->total,
        ]);
    }

    #[IsGranted('events.read')]
    #[Route('/{id}', methods: ['GET'], requirements: ['id' => self::ID])]
    public function get(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $details = $this->get->handle(new GetChurchEventQuery($id, $limit, $offset));

        return new JsonResponse([
            'event' => EventsJson::event($details->event),
            'series' => array_map(static fn (SeriesView $series): array => EventsJson::series($series), $details->series),
            'totalSeries' => $details->totalSeries,
        ]);
    }
}
