<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\CheckInChild\CheckInChildCommand;
use App\ChildrenMinistry\Sessions\Application\Command\CheckOutChild\CheckOutChildCommand;
use App\ChildrenMinistry\Sessions\Application\Command\IssuePickupLabel\IssuePickupLabelCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SupervisorCheckout\SupervisorCheckoutCommand;
use App\ChildrenMinistry\Sessions\Application\Command\TransferChild\TransferChildCommand;
use App\ChildrenMinistry\Sessions\Application\ExpectedChild;
use App\ChildrenMinistry\Sessions\Application\PickupCollector;
use App\ChildrenMinistry\Sessions\Application\Query\GetAttendance\GetAttendanceHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetAttendance\GetAttendanceQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceEvents\GetAttendanceEventsHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceEvents\GetAttendanceEventsQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceSegments\GetAttendanceSegmentsHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceSegments\GetAttendanceSegmentsQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetChildAttendance\GetChildAttendanceHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetChildAttendance\GetChildAttendanceQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetExpectedChildren\GetExpectedChildrenHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetExpectedChildren\GetExpectedChildrenQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetPickupCollectors\GetPickupCollectorsHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetPickupCollectors\GetPickupCollectorsQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetRoster\GetRosterHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetRoster\GetRosterQuery;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\ChildrenMinistry\Sessions\Presentation\Request\CheckInChildRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\CheckOutChildRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\IssuePickupLabelRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\SupervisorCheckoutRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\TransferChildRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Arrival, pickup labels, handover and transfer, and the reads around them. */
#[Route('/api/children')]
final readonly class AttendanceController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetAttendanceHandler $attendance,
        private GetAttendanceEventsHandler $events,
        private GetAttendanceSegmentsHandler $segments,
        private GetRosterHandler $roster,
        private GetExpectedChildrenHandler $expected,
        private GetPickupCollectorsHandler $collectors,
        private GetChildAttendanceHandler $childAttendance,
    ) {
    }

    #[IsGranted('children.station')]
    #[Route('/attendance', methods: ['POST'])]
    public function checkIn(#[MapRequestPayload] CheckInChildRequest $request): JsonResponse
    {
        $view = $this->commands->dispatch(new CheckInChildCommand(
            $this->account->requireAccount(),
            Json::uuid($request->sessionId ?? throw new BadRequestException('Session is required.')),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            Json::uuid($request->requestId ?? throw new BadRequestException('Request id is required.')),
        ));

        return new JsonResponse(AttendanceJson::view($view));
    }

    #[IsGranted('children.station')]
    #[Route('/attendance/labels', methods: ['POST'])]
    public function issueLabel(#[MapRequestPayload] IssuePickupLabelRequest $request): JsonResponse
    {
        $label = $this->commands->dispatch(new IssuePickupLabelCommand(
            $this->account->requireAccount(),
            Json::uuid($request->attendanceId ?? throw new BadRequestException('Attendance is required.')),
            $request->expectedVersion,
            $request->replacementReason,
        ));

        return new JsonResponse(AttendanceJson::label($label), headers: ['Cache-Control' => 'no-store']);
    }

    #[IsGranted('children.station')]
    #[Route('/attendance/checkout', methods: ['POST'])]
    public function checkOut(#[MapRequestPayload] CheckOutChildRequest $request): JsonResponse
    {
        $outcome = $this->commands->dispatch(new CheckOutChildCommand(
            $this->account->requireAccount(),
            Json::uuid($request->attendanceId ?? throw new BadRequestException('Attendance is required.')),
            $request->expectedVersion,
            Json::uuid($request->requestId ?? throw new BadRequestException('Request id is required.')),
            Json::uuid($request->collectorId ?? throw new BadRequestException('Collector is required.')),
            $request->identityVerified,
            $request->pickupCode,
        ));

        return new JsonResponse(AttendanceJson::view($outcome->attendance()));
    }

    #[IsGranted('children.supervise')]
    #[Route('/attendance/supervisor-checkout', methods: ['POST'])]
    public function supervisorCheckout(#[MapRequestPayload] SupervisorCheckoutRequest $request): JsonResponse
    {
        $outcome = $this->commands->dispatch(new SupervisorCheckoutCommand(
            $this->account->requireAccount(),
            Json::uuid($request->attendanceId ?? throw new BadRequestException('Attendance is required.')),
            $request->expectedVersion,
            Json::uuid($request->requestId ?? throw new BadRequestException('Request id is required.')),
            Json::uuid($request->collectorId ?? throw new BadRequestException('Collector is required.')),
            $request->identityVerified,
            $request->reason,
        ));

        return new JsonResponse(AttendanceJson::view($outcome->attendance()));
    }

    #[IsGranted('children.supervise')]
    #[Route('/attendance/transfers', methods: ['POST'])]
    public function transfer(#[MapRequestPayload] TransferChildRequest $request): JsonResponse
    {
        $view = $this->commands->dispatch(new TransferChildCommand(
            $this->account->requireAccount(),
            Json::uuid($request->attendanceId ?? throw new BadRequestException('Attendance is required.')),
            $request->expectedVersion,
            Json::uuid($request->targetSessionId ?? throw new BadRequestException('Target session is required.')),
            $request->reason,
        ));

        return new JsonResponse(AttendanceJson::view($view));
    }

    #[IsGranted('children.station')]
    #[Route('/sessions/{sessionId}/roster', methods: ['GET'])]
    public function roster(
        #[PathUuid] string $sessionId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOLEAN, flags: \FILTER_NULL_ON_FAILURE)] ?bool $openOnly = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $views = $this->roster->handle(new GetRosterQuery($this->account->requireAccount(), $sessionId, $openOnly ?? true, $limit, $offset));

        return new JsonResponse(array_map(AttendanceJson::view(...), $views));
    }

    #[IsGranted('children.station')]
    #[Route('/sessions/{sessionId}/expected-children', methods: ['GET'])]
    public function expectedChildren(
        #[PathUuid] string $sessionId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $children = $this->expected->handle(new GetExpectedChildrenQuery($this->account->requireAccount(), $sessionId, $limit, $offset));

        return new JsonResponse(array_map(static fn (ExpectedChild $child): array => AttendanceJson::expected($child), $children));
    }

    #[IsGranted('children.supervise')]
    #[Route('/attendance/{attendanceId}/events', methods: ['GET'])]
    public function events(
        #[PathUuid] string $attendanceId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $events = $this->events->handle(new GetAttendanceEventsQuery($attendanceId, $limit, $offset));

        return new JsonResponse(array_map(static fn (AttendanceEvent $event): array => AttendanceJson::event($event), $events));
    }

    #[IsGranted('children.read')]
    #[Route('/attendance/{attendanceId}/segments', methods: ['GET'])]
    public function segments(#[PathUuid] string $attendanceId): JsonResponse
    {
        $segments = $this->segments->handle(new GetAttendanceSegmentsQuery($attendanceId));

        return new JsonResponse(array_map(static fn (AttendanceSegment $segment): array => AttendanceJson::segment($segment), $segments));
    }

    #[IsGranted('children.station')]
    #[Route('/attendance/{attendanceId}/collectors', methods: ['GET'])]
    public function collectors(#[PathUuid] string $attendanceId): JsonResponse
    {
        $collectors = $this->collectors->handle(new GetPickupCollectorsQuery($this->account->requireAccount(), $attendanceId));

        return new JsonResponse(array_map(static fn (PickupCollector $collector): array => AttendanceJson::collector($collector), $collectors));
    }

    #[IsGranted('children.read')]
    #[Route('/attendance/children/{childId}', methods: ['GET'])]
    public function childVisits(
        #[PathUuid] string $childId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(AttendanceJson::view(...), $this->childAttendance->handle(new GetChildAttendanceQuery($childId, $limit, $offset))));
    }

    #[IsGranted('children.station')]
    #[Route('/attendance/{attendanceId}', methods: ['GET'])]
    public function get(#[PathUuid] string $attendanceId): JsonResponse
    {
        return new JsonResponse(AttendanceJson::view($this->attendance->handle(new GetAttendanceQuery($this->account->requireAccount(), $attendanceId))));
    }
}
