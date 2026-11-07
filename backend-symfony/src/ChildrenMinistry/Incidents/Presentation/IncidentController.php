<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Presentation;

use App\ChildrenMinistry\Incidents\Application\Command\AccessIncident\AccessIncidentCommand;
use App\ChildrenMinistry\Incidents\Application\Command\FollowUpIncident\FollowUpIncidentCommand;
use App\ChildrenMinistry\Incidents\Application\Command\PurgeExpiredIncidents\PurgeExpiredIncidentsCommand;
use App\ChildrenMinistry\Incidents\Application\Command\ReportIncident\ReportIncidentCommand;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidentAttendance\ListIncidentAttendanceHandler;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidentAttendance\ListIncidentAttendanceQuery;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidentGuardians\ListIncidentGuardiansHandler;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidentGuardians\ListIncidentGuardiansQuery;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidentOwners\ListIncidentOwnersHandler;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidentOwners\ListIncidentOwnersQuery;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidents\ListIncidentsHandler;
use App\ChildrenMinistry\Incidents\Application\Query\ListIncidents\ListIncidentsQuery;
use App\ChildrenMinistry\Incidents\Presentation\Request\AccessIncidentRequest;
use App\ChildrenMinistry\Incidents\Presentation\Request\FollowUpIncidentRequest;
use App\ChildrenMinistry\Incidents\Presentation\Request\PurgeIncidentsRequest;
use App\ChildrenMinistry\Incidents\Presentation\Request\ReportIncidentRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Restricted incidents. Reporting needs children.incidents.record (with a place on the session) or .manage; everything
 * else needs .manage. Handlers repeat the permission checks; reporting and attendance lookup have no single HTTP
 * attribute because either permission is enough.
 */
#[Route('/api/children/incidents')]
final readonly class IncidentController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListIncidentsHandler $list,
        private ListIncidentAttendanceHandler $attendance,
        private ListIncidentGuardiansHandler $guardians,
        private ListIncidentOwnersHandler $owners,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function report(#[MapRequestPayload] ReportIncidentRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new ReportIncidentCommand(
            $this->account->requireAccount(),
            Json::uuid((string) $request->requestId),
            Json::uuid((string) $request->childId),
            Json::uuid((string) $request->sessionId),
            Json::uuid((string) $request->segmentId),
            $request->category,
            $request->severity,
            Json::parseInstant((string) $request->occurredAt),
            $request->description,
            $request->immediateAction,
            CalendarDate::parse((string) $request->reviewOn),
            CalendarDate::parse((string) $request->retainUntil),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.incidents.manage')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $sessionId = null,
        #[MapQueryParameter] ?string $childId = null,
        #[MapQueryParameter] string $status = 'ACTIVE',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOLEAN)] bool $overdueOnly = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(IncidentJson::list($this->list->handle(new ListIncidentsQuery(
            null === $sessionId ? null : Json::uuid($sessionId),
            null === $childId ? null : Json::uuid($childId),
            $status,
            $overdueOnly,
            $limit,
            $offset,
        ))));
    }

    #[Route('/attendance', methods: ['GET'])]
    public function attendance(
        #[MapQueryParameter] ?string $sessionId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(IncidentJson::attendance($this->attendance->handle(new ListIncidentAttendanceQuery(
            $this->account->requireAccount(),
            Json::uuid($sessionId ?? throw new BadRequestException('Session is required.')),
            $search,
            $limit,
            $offset,
        ))));
    }

    #[IsGranted('children.incidents.manage')]
    #[Route('/owners', methods: ['GET'])]
    public function owners(#[MapQueryParameter] string $search = ''): JsonResponse
    {
        return new JsonResponse(array_map(IncidentJson::owner(...), $this->owners->handle(new ListIncidentOwnersQuery($search))));
    }

    #[IsGranted('children.incidents.manage')]
    #[Route('/purge-expired', methods: ['POST'])]
    public function purge(#[MapRequestPayload] PurgeIncidentsRequest $request): JsonResponse
    {
        return new JsonResponse(['purged' => $this->commands->dispatch(new PurgeExpiredIncidentsCommand($this->account->requireAccount(), $request->limit))]);
    }

    #[IsGranted('children.incidents.manage')]
    #[Route('/{id}/guardians', methods: ['GET'])]
    public function guardians(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(array_map(IncidentJson::guardian(...), $this->guardians->handle(new ListIncidentGuardiansQuery($id))));
    }

    #[IsGranted('children.incidents.manage')]
    #[Route('/{id}/access', methods: ['POST'])]
    public function access(#[PathUuid] string $id, #[MapRequestPayload] AccessIncidentRequest $request): JsonResponse
    {
        $response = new JsonResponse(IncidentJson::access($this->commands->dispatch(new AccessIncidentCommand($this->account->requireAccount(), $id, $request->limit, $request->offset))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[IsGranted('children.incidents.manage')]
    #[Route('/{id}/follow-up', methods: ['POST'])]
    public function followUp(#[PathUuid] string $id, #[MapRequestPayload] FollowUpIncidentRequest $request): Response
    {
        $this->commands->dispatch(new FollowUpIncidentCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->action,
            null === $request->ownerId ? null : Json::uuid($request->ownerId),
            null === $request->reviewOn ? null : CalendarDate::parse($request->reviewOn),
            null === $request->guardianAuthorityId ? null : Json::uuid($request->guardianAuthorityId),
            $request->note,
        ));

        return new Response(status: 204);
    }
}
