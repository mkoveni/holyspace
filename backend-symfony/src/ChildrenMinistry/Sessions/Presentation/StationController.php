<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Query\GetSessionDay\GetSessionDayHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetSessionDay\GetSessionDayQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetStationRoster\GetStationRosterHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetStationRoster\GetStationRosterQuery;
use App\ChildrenMinistry\Sessions\Application\Query\GetStationSession\GetStationSessionHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetStationSession\GetStationSessionQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListStationSessions\ListStationSessionsHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListStationSessions\ListStationSessionsQuery;
use App\ChildrenMinistry\Sessions\Application\StationAttendance;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The check-in station's view of sessions: present staff see their own, supervisors see all. */
#[Route('/api/children/station/sessions')]
#[IsGranted('children.station')]
final readonly class StationController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private ListStationSessionsHandler $list,
        private GetStationSessionHandler $get,
        private GetStationRosterHandler $roster,
        private GetSessionDayHandler $day,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $from = null,
        #[MapQueryParameter] ?string $until = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $views = $this->list->handle(new ListStationSessionsQuery(
            $this->account->requireAccount(),
            null === $from ? null : Json::parseInstant($from),
            null === $until ? null : Json::parseInstant($until),
            $limit,
            $offset,
        ));

        return new JsonResponse(SessionJson::views($views));
    }

    #[Route('/{sessionId}', methods: ['GET'])]
    public function get(#[PathUuid] string $sessionId): JsonResponse
    {
        return new JsonResponse(SessionJson::view($this->get->handle(new GetStationSessionQuery($this->account->requireAccount(), $sessionId))));
    }

    #[Route('/{sessionId}/attendance', methods: ['GET'])]
    public function attendance(
        #[PathUuid] string $sessionId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 100,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOLEAN, flags: \FILTER_NULL_ON_FAILURE)] ?bool $openOnly = null,
    ): JsonResponse {
        $rows = $this->roster->handle(new GetStationRosterQuery($this->account->requireAccount(), $sessionId, $limit, $offset, $openOnly ?? true));

        return new JsonResponse(array_map(static fn (StationAttendance $row): array => AttendanceJson::station($row), $rows));
    }

    #[Route('/{sessionId}/day', methods: ['GET'])]
    public function day(
        #[PathUuid] string $sessionId,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] string $view = 'EXPECTED',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 100,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(AttendanceJson::day($this->day->handle(new GetSessionDayQuery($this->account->requireAccount(), $sessionId, $search, $view, $limit, $offset))));
    }
}
