<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communities\Application\Command\AddGroupGuest\AddGroupGuestCommand;
use App\Communities\Application\Command\FinishGroupMeeting\FinishGroupMeetingCommand;
use App\Communities\Application\Command\PrepareGroupRoster\PrepareGroupRosterCommand;
use App\Communities\Application\Command\RecordGroupAttendance\RecordGroupAttendanceCommand;
use App\Communities\Application\Command\ScheduleGroupMeetings\ScheduleGroupMeetingsCommand;
use App\Communities\Application\Query\GetGroupRoster\GetGroupRosterHandler;
use App\Communities\Application\Query\GetGroupRoster\GetGroupRosterQuery;
use App\Communities\Application\Query\ListGroupMeetings\ListGroupMeetingsHandler;
use App\Communities\Application\Query\ListGroupMeetings\ListGroupMeetingsQuery;
use App\Communities\Application\Query\PreviewGroupSchedule\PreviewGroupScheduleHandler;
use App\Communities\Application\Query\PreviewGroupSchedule\PreviewGroupScheduleQuery;
use App\Communities\Domain\CommunityException;
use App\Communities\Presentation\Request\AddGroupGuestRequest;
use App\Communities\Presentation\Request\CommunityScheduleRequest;
use App\Communities\Presentation\Request\FinishGroupMeetingRequest;
use App\Communities\Presentation\Request\PrepareGroupRosterRequest;
use App\Communities\Presentation\Request\PreviewGroupScheduleRequest;
use App\Communities\Presentation\Request\RecordGroupAttendanceRequest;
use App\Communities\Presentation\Request\ScheduleGroupMeetingsRequest;
use App\Events\Application\Contract\CommunitySchedule;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Group meetings scheduled through Events, frozen rosters, guests and attendance (Java `CommunityMeetingsController`). */
#[IsGranted('ministries.read|ministries.manage|ministries.lead')]
final readonly class CommunityMeetingsController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private PreviewGroupScheduleHandler $preview,
        private ListGroupMeetingsHandler $meetings,
        private GetGroupRosterHandler $roster,
    ) {
    }

    #[Route('/api/communities/{communityId}/meetings', methods: ['POST'])]
    public function schedule(#[PathUuid] string $communityId, #[MapRequestPayload] ScheduleGroupMeetingsRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new ScheduleGroupMeetingsCommand(
            $this->account->requireAccount(),
            CommunitiesJson::uuid($request->requestId),
            $communityId,
            $request->expectedVersion,
            $this->toSchedule($request->schedule),
            (string) $request->reviewHash,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/api/communities/{communityId}/meeting-preview', methods: ['POST'])]
    public function previewSchedule(#[PathUuid] string $communityId, #[MapRequestPayload] PreviewGroupScheduleRequest $request): JsonResponse
    {
        return new JsonResponse(CommunitiesJson::preview($this->preview->handle(new PreviewGroupScheduleQuery($communityId, $this->toSchedule($request->schedule)))));
    }

    #[Route('/api/communities/meetings/{meetingId}/prepare', methods: ['POST'])]
    public function prepare(#[PathUuid] string $meetingId, #[MapRequestPayload] PrepareGroupRosterRequest $request): Response
    {
        $this->commands->dispatch(new PrepareGroupRosterCommand($this->account->requireAccount(), $meetingId, $request->expectedVersion));

        return new Response(status: 204);
    }

    #[Route('/api/communities/meetings/{meetingId}/guests', methods: ['POST'])]
    public function addGuest(#[PathUuid] string $meetingId, #[MapRequestPayload] AddGroupGuestRequest $request): Response
    {
        $this->commands->dispatch(new AddGroupGuestCommand($this->account->requireAccount(), $meetingId, $request->expectedVersion, (string) CommunitiesJson::uuid($request->personId), $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/communities/roster/{rosterId}', methods: ['PUT'])]
    public function recordAttendance(#[PathUuid] string $rosterId, #[MapRequestPayload] RecordGroupAttendanceRequest $request): Response
    {
        $this->commands->dispatch(new RecordGroupAttendanceCommand($this->account->requireAccount(), $rosterId, $request->expectedVersion, $request->status, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/communities/meetings/{meetingId}/finish', methods: ['POST'])]
    public function finish(#[PathUuid] string $meetingId, #[MapRequestPayload] FinishGroupMeetingRequest $request): Response
    {
        $this->commands->dispatch(new FinishGroupMeetingCommand($this->account->requireAccount(), $meetingId, $request->expectedVersion, $request->status, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/communities/{communityId}/meetings', methods: ['GET'])]
    public function list(
        #[PathUuid] string $communityId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(CommunitiesJson::meetings($this->meetings->handle(new ListGroupMeetingsQuery($communityId, $limit, $offset))));
    }

    #[Route('/api/communities/meetings/{meetingId}/roster', methods: ['GET'])]
    public function roster(
        #[PathUuid] string $meetingId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(CommunitiesJson::roster($this->roster->handle(new GetGroupRosterQuery($meetingId, $limit, $offset))));
    }

    /** Validation already refused a missing schedule; this only narrows the type. */
    private function toSchedule(?CommunityScheduleRequest $schedule): CommunitySchedule
    {
        return ($schedule ?? throw CommunityException::invalid('Choose a schedule.'))->schedule();
    }
}
