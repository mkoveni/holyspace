<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerCommand;
use App\Volunteering\Application\Command\BulkCreateVolunteerShifts\BulkCreateVolunteerShiftsCommand;
use App\Volunteering\Application\Command\ChangeVolunteerAssignment\ChangeVolunteerAssignmentCommand;
use App\Volunteering\Application\Command\CreateVolunteerShift\CreateVolunteerShiftCommand;
use App\Volunteering\Application\Command\UpdateVolunteerShift\UpdateVolunteerShiftCommand;
use App\Volunteering\Application\Query\GetAssignmentHistory\GetAssignmentHistoryHandler;
use App\Volunteering\Application\Query\GetAssignmentHistory\GetAssignmentHistoryQuery;
use App\Volunteering\Application\Query\GetVolunteerReadiness\GetVolunteerReadinessHandler;
use App\Volunteering\Application\Query\GetVolunteerReadiness\GetVolunteerReadinessQuery;
use App\Volunteering\Application\Query\GetVolunteerShift\GetVolunteerShiftHandler;
use App\Volunteering\Application\Query\GetVolunteerShift\GetVolunteerShiftQuery;
use App\Volunteering\Application\Query\ListVolunteerActivities\ListVolunteerActivitiesHandler;
use App\Volunteering\Application\Query\ListVolunteerActivities\ListVolunteerActivitiesQuery;
use App\Volunteering\Application\Query\ListVolunteerShifts\ListVolunteerShiftsHandler;
use App\Volunteering\Application\Query\ListVolunteerShifts\ListVolunteerShiftsQuery;
use App\Volunteering\Application\Query\PreviewVolunteerSchedule\PreviewVolunteerScheduleHandler;
use App\Volunteering\Application\Query\PreviewVolunteerSchedule\PreviewVolunteerScheduleQuery;
use App\Volunteering\Presentation\Request\AssignmentEditRequest;
use App\Volunteering\Presentation\Request\AssignRequest;
use App\Volunteering\Presentation\Request\ShiftEditRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Java `VolunteerRosterController` and `VolunteerSchedulePreviewController`: shifts, assignments, readiness, activities. */
final readonly class VolunteerRosterController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListVolunteerShiftsHandler $shifts,
        private GetVolunteerShiftHandler $shift,
        private GetVolunteerReadinessHandler $readiness,
        private ListVolunteerActivitiesHandler $activities,
        private GetAssignmentHistoryHandler $history,
        private PreviewVolunteerScheduleHandler $preview,
    ) {
    }

    #[Route('/api/volunteering/shifts', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function createShift(Request $request): JsonResponse
    {
        $draft = VolunteeringInput::draft($request->toArray());

        return new JsonResponse(['id' => $this->commands->dispatch(new CreateVolunteerShiftCommand($this->account->requireAccount(), $draft))], 201);
    }

    #[Route('/api/volunteering/shifts/bulk', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function bulkShifts(Request $request): JsonResponse
    {
        $drafts = VolunteeringInput::drafts($request->toArray());

        return new JsonResponse($this->commands->dispatch(new BulkCreateVolunteerShiftsCommand($this->account->requireAccount(), $drafts)));
    }

    #[Route('/api/volunteering/shifts/preview', methods: ['POST'])]
    #[IsGranted('volunteering.read')]
    public function previewShifts(Request $request): JsonResponse
    {
        $body = $request->toArray();

        return new JsonResponse(array_map(VolunteeringJson::draft(...), $this->preview->handle(new PreviewVolunteerScheduleQuery(
            VolunteeringInput::uuid($body, 'requestId'),
            VolunteeringInput::uuid($body, 'teamId'),
            VolunteeringInput::uuid($body, 'roleId'),
            VolunteeringInput::dates($body, 'dates'),
            VolunteeringInput::localTime($body, 'startsAt'),
            VolunteeringInput::localTime($body, 'endsAt'),
            VolunteeringInput::string($body, 'timeZone'),
            VolunteeringInput::int($body, 'positions'),
        ))));
    }

    #[Route('/api/volunteering/shifts/{id}', methods: ['PUT'])]
    #[IsGranted('volunteering.manage')]
    public function updateShift(#[PathUuid] string $id, #[MapRequestPayload] ShiftEditRequest $request): Response
    {
        $this->commands->dispatch(new UpdateVolunteerShiftCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::parseInstant((string) $request->startsAt),
            Json::parseInstant((string) $request->endsAt),
            $request->positions,
            $request->status,
        ));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/assignments', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function assign(#[MapRequestPayload] AssignRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new AssignVolunteerCommand(
            $this->account->requireAccount(),
            Json::uuid((string) $request->requestId),
            Json::uuid((string) $request->shiftId),
            $request->expectedVersion,
            Json::uuid((string) $request->volunteerId),
            $request->status,
        ))], 201);
    }

    #[Route('/api/volunteering/assignments/{id}/status', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function changeAssignment(#[PathUuid] string $id, #[MapRequestPayload] AssignmentEditRequest $request): Response
    {
        $this->commands->dispatch(new ChangeVolunteerAssignmentCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->status, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/shifts', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function listShifts(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $until,
        #[MapQueryParameter(validationFailedStatusCode: 400)] ?string $teamId = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(VolunteeringJson::shifts($this->shifts->handle(new ListVolunteerShiftsQuery(
            Json::parseInstant($from),
            Json::parseInstant($until),
            null === $teamId ? null : Json::uuid($teamId),
            $limit,
            $offset,
        ))));
    }

    #[Route('/api/volunteering/shifts/{id}', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function getShift(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(VolunteeringJson::shiftDetails($this->shift->handle(new GetVolunteerShiftQuery($id))));
    }

    #[Route('/api/volunteering/readiness', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function getReadiness(#[MapQueryParameter(validationFailedStatusCode: 400)] string $volunteerId, #[MapQueryParameter(validationFailedStatusCode: 400)] string $shiftId): JsonResponse
    {
        return new JsonResponse(VolunteeringJson::readiness($this->readiness->handle(new GetVolunteerReadinessQuery(Json::uuid($volunteerId), Json::uuid($shiftId)))));
    }

    #[Route('/api/volunteering/activities', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function listActivities(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $kind,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $until,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 100,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(VolunteeringJson::activity(...), $this->activities->handle(new ListVolunteerActivitiesQuery($kind, Json::parseInstant($from), Json::parseInstant($until), $limit, $offset))));
    }

    #[Route('/api/volunteering/assignments/{id}/events', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function getHistory(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(VolunteeringJson::event(...), $this->history->handle(new GetAssignmentHistoryQuery($id, $limit, $offset))));
    }
}
