<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\Controller;

use App\Ministries\Application\Command\CancelGroupMeeting\CancelGroupMeetingCommand;
use App\Ministries\Application\Command\CancelGroupMeeting\CancelGroupMeetingHandler;
use App\Ministries\Application\Command\CreateGroupMeeting\CreateGroupMeetingCommand;
use App\Ministries\Application\Command\CreateGroupMeeting\CreateGroupMeetingHandler;
use App\Ministries\Application\Command\MarkGroupAttendance\MarkGroupAttendanceCommand;
use App\Ministries\Application\Command\MarkGroupAttendance\MarkGroupAttendanceHandler;
use App\Ministries\Application\Query\GetGroupWorkspace\GetGroupWorkspaceHandler;
use App\Ministries\Application\Query\GetGroupWorkspace\GetGroupWorkspaceQuery;
use App\Ministries\Application\Query\GetLedGroups\GetLedGroupsHandler;
use App\Ministries\Application\Query\GetLedGroups\GetLedGroupsQuery;
use App\Ministries\Presentation\Http\DTO\CancelMeetingPayload;
use App\Ministries\Presentation\Http\DTO\GroupAttendancePayload;
use App\Ministries\Presentation\Http\DTO\MeetingPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/groups', format: 'json')]
final class GroupWorkspaceController extends WorkflowController
{
    public function __construct(
        private readonly GetLedGroupsHandler $getLedGroupsHandler,
        private readonly GetGroupWorkspaceHandler $getGroupWorkspaceHandler,
        private readonly CreateGroupMeetingHandler $createGroupMeetingHandler,
        private readonly CancelGroupMeetingHandler $cancelGroupMeetingHandler,
        private readonly MarkGroupAttendanceHandler $markGroupAttendanceHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function groups(): JsonResponse
    {
        return $this->result(fn () => ($this->getLedGroupsHandler)(new GetLedGroupsQuery()));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function overview(string $id): JsonResponse
    {
        $r = $this->result(fn () => ($this->getGroupWorkspaceHandler)(new GetGroupWorkspaceQuery($id)));
        $r->headers->set('Cache-Control', 'no-store');

        return $r;
    }

    #[Route('/{id}/meetings', methods: ['POST'])]
    public function create(
        string $id,
        #[MapRequestPayload]
        MeetingPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createGroupMeetingHandler)(new CreateGroupMeetingCommand($id, $p->occurrenceId)), 201);
    }

    #[Route('/{groupId}/meetings/{id}/cancel', methods: ['POST'])]
    public function cancel(
        string $groupId,
        string $id,
        #[MapRequestPayload]
        CancelMeetingPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->cancelGroupMeetingHandler)(new CancelGroupMeetingCommand($groupId, $id, $p->expectedVersion, $p->reason)));
    }

    #[Route('/{groupId}/meetings/{id}/attendance', methods: ['POST'])]
    public function attendance(
        string $groupId,
        string $id,
        #[MapRequestPayload]
        GroupAttendancePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->markGroupAttendanceHandler)(new MarkGroupAttendanceCommand($groupId, $id, $p->personId, $p->status, $p->expectedVersion, $p->reason)));
    }
}
