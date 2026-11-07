<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\IssueMyChildPickupLabel\IssueMyChildPickupLabelCommand;
use App\ChildrenMinistry\Sessions\Application\Query\GetMyChild\GetMyChildHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetMyChild\GetMyChildQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildAttendance\ListMyChildAttendanceHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildAttendance\ListMyChildAttendanceQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildProgrammes\ListMyChildProgrammesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildProgrammes\ListMyChildProgrammesQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildren\ListMyChildrenHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildren\ListMyChildrenQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildSessions\ListMyChildSessionsHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListMyChildSessions\ListMyChildSessionsQuery;
use App\ChildrenMinistry\Sessions\Presentation\Request\MyChildPickupLabelRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A verified guardian's own children. No staff permission is involved or granted: every handler resolves the caller's
 * verified person and checks live guardian authority, so a child id in the URL is never an entitlement.
 */
#[Route('/api/member/children')]
final readonly class MemberFamilyController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListMyChildrenHandler $children,
        private GetMyChildHandler $child,
        private ListMyChildProgrammesHandler $programmes,
        private ListMyChildSessionsHandler $sessions,
        private ListMyChildAttendanceHandler $attendance,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function children(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $this->account->requireAccount();

        return self::private(MemberFamilyJson::page($this->children->handle(new ListMyChildrenQuery($limit, $offset)), MemberFamilyJson::child(...)));
    }

    #[Route('/{childId}', methods: ['GET'])]
    public function child(#[PathUuid] string $childId): JsonResponse
    {
        return self::private(MemberFamilyJson::child($this->child->handle(new GetMyChildQuery($this->account->requireAccount(), $childId))));
    }

    #[Route('/{childId}/programmes', methods: ['GET'])]
    public function programmes(
        #[PathUuid] string $childId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 10,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return self::private(MemberFamilyJson::page($this->programmes->handle(new ListMyChildProgrammesQuery($this->account->requireAccount(), $childId, $limit, $offset)), MemberFamilyJson::programme(...)));
    }

    #[Route('/{childId}/sessions', methods: ['GET'])]
    public function sessions(
        #[PathUuid] string $childId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 10,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return self::private(MemberFamilyJson::page($this->sessions->handle(new ListMyChildSessionsQuery($this->account->requireAccount(), $childId, $limit, $offset)), MemberFamilyJson::session(...)));
    }

    #[Route('/{childId}/attendance', methods: ['GET'])]
    public function attendance(
        #[PathUuid] string $childId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 10,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return self::private(MemberFamilyJson::page($this->attendance->handle(new ListMyChildAttendanceQuery($this->account->requireAccount(), $childId, $limit, $offset)), MemberFamilyJson::visit(...)));
    }

    #[Route('/{childId}/attendance/{attendanceId}/pickup-label', methods: ['POST'])]
    public function label(#[PathUuid] string $childId, #[PathUuid] string $attendanceId, #[MapRequestPayload] MyChildPickupLabelRequest $request): JsonResponse
    {
        $label = $this->commands->dispatch(new IssueMyChildPickupLabelCommand($this->account->requireAccount(), $childId, $attendanceId, $request->expectedVersion, $request->replacePrevious));

        return self::private(AttendanceJson::label($label));
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function private(array $body): JsonResponse
    {
        $response = new JsonResponse($body);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
