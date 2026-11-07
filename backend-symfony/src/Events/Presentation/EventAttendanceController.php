<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CheckInEventPerson\CheckInEventPersonCommand;
use App\Events\Application\Command\CorrectEventAttendance\CorrectEventAttendanceCommand;
use App\Events\Application\Command\RegisterEventWalkIn\RegisterEventWalkInCommand;
use App\Events\Application\Contract\AttendanceHistoryEntry;
use App\Events\Application\Query\ExportEventAttendanceReport\ExportEventAttendanceReportHandler;
use App\Events\Application\Query\ExportEventAttendanceReport\ExportEventAttendanceReportQuery;
use App\Events\Application\Query\GetEventAttendanceHistory\GetEventAttendanceHistoryHandler;
use App\Events\Application\Query\GetEventAttendanceHistory\GetEventAttendanceHistoryQuery;
use App\Events\Application\Query\GetEventAttendanceReport\GetEventAttendanceReportHandler;
use App\Events\Application\Query\GetEventAttendanceReport\GetEventAttendanceReportQuery;
use App\Events\Application\Query\ListEventAttendance\ListEventAttendanceHandler;
use App\Events\Application\Query\ListEventAttendance\ListEventAttendanceQuery;
use App\Events\Application\WalkIn;
use App\Events\Presentation\Request\CheckInEventPersonRequest;
use App\Events\Presentation\Request\CorrectEventAttendanceRequest;
use App\Events\Presentation\Request\RegisterEventWalkInRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Check-in, walk-ins, corrections, the roster and attendance reports. Reads need events.attendance.read, writes .manage. */
#[Route('/api/events')]
final readonly class EventAttendanceController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListEventAttendanceHandler $roster,
        private GetEventAttendanceHistoryHandler $history,
        private GetEventAttendanceReportHandler $report,
        private ExportEventAttendanceReportHandler $export,
    ) {
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/registrations/{id}/check-in', methods: ['POST'])]
    public function checkIn(#[PathUuid] string $id, #[MapRequestPayload] CheckInEventPersonRequest $request): JsonResponse
    {
        $attendance = $this->commands->dispatch(new CheckInEventPersonCommand($this->account->requireAccount(), strtolower((string) $request->requestId), $id, $request->expectedVersion));

        return new JsonResponse(['id' => $attendance], 201);
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/occurrences/{id}/walk-ins', methods: ['POST'])]
    public function walkIn(#[PathUuid] string $id, #[MapRequestPayload] RegisterEventWalkInRequest $request): JsonResponse
    {
        /** @var WalkIn $walkIn */
        $walkIn = $this->commands->dispatch(new RegisterEventWalkInCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            strtolower((string) $request->personId),
            $request->expectedVersion,
        ));

        return new JsonResponse(EventsJson::walkIn($walkIn), 201);
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/registrations/{id}/attendance-correction', methods: ['POST'])]
    public function correct(#[PathUuid] string $id, #[MapRequestPayload] CorrectEventAttendanceRequest $request): Response
    {
        $this->commands->dispatch(new CorrectEventAttendanceCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->status, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('events.attendance.read')]
    #[Route('/occurrences/{id}/attendance', methods: ['GET'])]
    public function roster(
        #[PathUuid] string $id,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(EventsJson::roster($this->roster->handle(new ListEventAttendanceQuery($id, $search, $status, $limit, $offset))));
    }

    #[IsGranted('events.attendance.read')]
    #[Route('/registrations/{id}/attendance-history', methods: ['GET'])]
    public function history(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(
            static fn (AttendanceHistoryEntry $entry): array => EventsJson::attendanceEntry($entry),
            $this->history->handle(new GetEventAttendanceHistoryQuery($id, $limit, $offset)),
        ));
    }

    #[IsGranted('events.attendance.read')]
    #[Route('/attendance-report', methods: ['GET'])]
    public function report(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $until,
        #[MapQueryParameter] ?string $eventId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->report->handle(new GetEventAttendanceReportQuery(null === $eventId ? null : Json::uuid($eventId), $search, Json::parseInstant($from), Json::parseInstant($until), $limit, $offset));

        return new JsonResponse(EventsJson::report($page));
    }

    #[IsGranted('events.attendance.read')]
    #[Route('/attendance-report.csv', methods: ['GET'])]
    public function exportReport(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $until,
        #[MapQueryParameter] ?string $eventId = null,
        #[MapQueryParameter] string $search = '',
    ): Response {
        $csv = $this->export->handle(new ExportEventAttendanceReportQuery(null === $eventId ? null : Json::uuid($eventId), $search, Json::parseInstant($from), Json::parseInstant($until)));

        return new Response($csv, 200, ['Content-Type' => 'text/csv;charset=UTF-8', 'Content-Disposition' => 'attachment; filename=event-attendance.csv']);
    }
}
