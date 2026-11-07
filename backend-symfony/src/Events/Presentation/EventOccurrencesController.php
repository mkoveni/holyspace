<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CancelEventOccurrences\CancelEventOccurrencesCommand;
use App\Events\Application\Command\RescheduleEventOccurrences\RescheduleEventOccurrencesCommand;
use App\Events\Application\Command\RestoreEventOccurrence\RestoreEventOccurrenceCommand;
use App\Events\Application\Contract\CalendarItem;
use App\Events\Application\Contract\OccurrenceHistoryEntry;
use App\Events\Application\OccurrenceChangeDraft;
use App\Events\Application\Query\GetEventOccurrence\GetEventOccurrenceHandler;
use App\Events\Application\Query\GetEventOccurrence\GetEventOccurrenceQuery;
use App\Events\Application\Query\GetEventOccurrenceHistory\GetEventOccurrenceHistoryHandler;
use App\Events\Application\Query\GetEventOccurrenceHistory\GetEventOccurrenceHistoryQuery;
use App\Events\Application\Query\ListEventCalendar\ListEventCalendarHandler;
use App\Events\Application\Query\ListEventCalendar\ListEventCalendarQuery;
use App\Events\Application\Query\PreviewEventOccurrenceChange\PreviewEventOccurrenceChangeHandler;
use App\Events\Application\Query\PreviewEventOccurrenceChange\PreviewEventOccurrenceChangeQuery;
use App\Events\Presentation\Request\OccurrenceChangeRequest;
use App\Events\Presentation\Request\PreviewOccurrenceChangeRequest;
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

/** The occurrence calendar and reviewed schedule changes. Reads need events.read, changes events.manage. */
#[Route('/api/events')]
final readonly class EventOccurrencesController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private PreviewEventOccurrenceChangeHandler $preview,
        private ListEventCalendarHandler $calendar,
        private GetEventOccurrenceHandler $get,
        private GetEventOccurrenceHistoryHandler $history,
    ) {
    }

    #[IsGranted('events.manage')]
    #[Route('/occurrences/reschedule', methods: ['POST'])]
    public function reschedule(#[MapRequestPayload] OccurrenceChangeRequest $request): Response
    {
        $this->commands->dispatch(new RescheduleEventOccurrencesCommand($this->account->requireAccount(), $this->draft($request), $request->reviewHash, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('events.manage')]
    #[Route('/occurrences/cancel', methods: ['POST'])]
    public function cancel(#[MapRequestPayload] OccurrenceChangeRequest $request): Response
    {
        $this->commands->dispatch(new CancelEventOccurrencesCommand($this->account->requireAccount(), $this->draft($request), $request->reviewHash, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('events.manage')]
    #[Route('/occurrences/restore', methods: ['POST'])]
    public function restore(#[MapRequestPayload] OccurrenceChangeRequest $request): Response
    {
        $this->commands->dispatch(new RestoreEventOccurrenceCommand($this->account->requireAccount(), $this->draft($request), $request->reviewHash, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('events.read')]
    #[Route('/occurrences/preview', methods: ['POST'])]
    public function preview(#[MapRequestPayload] PreviewOccurrenceChangeRequest $request): JsonResponse
    {
        $draft = ($request->draft ?? throw new BadRequestException('A draft is required.'))->draft();

        return new JsonResponse(EventsJson::changePreview($this->preview->handle(new PreviewEventOccurrenceChangeQuery($draft))));
    }

    #[IsGranted('events.read')]
    #[Route('/calendar', methods: ['GET'])]
    public function calendar(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $until,
        #[MapQueryParameter] ?string $eventId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->calendar->handle(new ListEventCalendarQuery(
            null === $eventId ? null : Json::uuid($eventId),
            $search,
            Json::parseInstant($from),
            Json::parseInstant($until),
            $status,
            $limit,
            $offset,
        ));

        return new JsonResponse([
            'occurrences' => array_map(static fn (CalendarItem $item): array => EventsJson::calendarItem($item), $page->occurrences),
            'total' => $page->total,
        ]);
    }

    #[IsGranted('events.read')]
    #[Route('/occurrences/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(EventsJson::occurrenceDetails($this->get->handle(new GetEventOccurrenceQuery($id))));
    }

    #[IsGranted('events.read')]
    #[Route('/occurrences/{id}/history', methods: ['GET'])]
    public function history(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(
            static fn (OccurrenceHistoryEntry $entry): array => EventsJson::occurrenceEntry($entry),
            $this->history->handle(new GetEventOccurrenceHistoryQuery($id, $limit, $offset)),
        ));
    }

    private function draft(OccurrenceChangeRequest $request): OccurrenceChangeDraft
    {
        return ($request->draft ?? throw new BadRequestException('A draft is required.'))->draft();
    }
}
