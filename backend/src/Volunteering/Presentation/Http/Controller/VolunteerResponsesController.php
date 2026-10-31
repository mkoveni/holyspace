<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\Controller;

use App\Shared\Presentation\Http\WorkflowController;
use App\Volunteering\Application\Command\InviteVolunteer\InviteVolunteerCommand;
use App\Volunteering\Application\Command\InviteVolunteer\InviteVolunteerHandler;
use App\Volunteering\Application\Command\RespondToInvitation\RespondToInvitationCommand;
use App\Volunteering\Application\Command\RespondToInvitation\RespondToInvitationHandler;
use App\Volunteering\Application\Command\SendVolunteerReminder\SendVolunteerReminderCommand;
use App\Volunteering\Application\Command\SendVolunteerReminder\SendVolunteerReminderHandler;
use App\Volunteering\Application\Query\GetOwnVolunteerSchedule\GetOwnVolunteerScheduleHandler;
use App\Volunteering\Application\Query\GetOwnVolunteerSchedule\GetOwnVolunteerScheduleQuery;
use App\Volunteering\Application\Query\GetVolunteerManagerOverview\GetVolunteerManagerOverviewHandler;
use App\Volunteering\Application\Query\GetVolunteerManagerOverview\GetVolunteerManagerOverviewQuery;
use App\Volunteering\Presentation\Http\DTO\InvitationPayload;
use App\Volunteering\Presentation\Http\DTO\InvitationResponsePayload;
use App\Volunteering\Presentation\Http\DTO\ReminderPayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class VolunteerResponsesController extends WorkflowController
{
    public function __construct(
        private readonly GetVolunteerManagerOverviewHandler $getVolunteerManagerOverviewHandler,
        private readonly GetOwnVolunteerScheduleHandler $getOwnVolunteerScheduleHandler,
        private readonly InviteVolunteerHandler $inviteVolunteerHandler,
        private readonly RespondToInvitationHandler $respondToInvitationHandler,
        private readonly SendVolunteerReminderHandler $sendVolunteerReminderHandler,
    ) {
    }

    #[Route('/api/volunteer-rota', methods: ['GET'], format: 'json')]
    public function manager(): JsonResponse
    {
        return $this->result(fn () => ($this->getVolunteerManagerOverviewHandler)(new GetVolunteerManagerOverviewQuery()));
    }

    #[Route('/api/volunteer-workspace', methods: ['GET'], format: 'json')]
    public function own(): JsonResponse
    {
        $r = $this->result(fn () => ($this->getOwnVolunteerScheduleHandler)(new GetOwnVolunteerScheduleQuery()));
        $r->headers->set('Cache-Control', 'no-store');

        return $r;
    }

    #[Route('/api/volunteer-rota/invitations', methods: ['POST'], format: 'json')]
    public function invite(
        #[MapRequestPayload]
        InvitationPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->inviteVolunteerHandler)(new InviteVolunteerCommand($p->shiftId, $p->personId, $p->expiresAt)), 201);
    }

    #[Route('/api/volunteer-workspace/invitations/{id}/response', methods: ['POST'], format: 'json')]
    public function respond(
        string $id,
        #[MapRequestPayload]
        InvitationResponsePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->respondToInvitationHandler)(new RespondToInvitationCommand($id, $p->decision, $p->expectedVersion)));
    }

    #[Route('/api/volunteer-rota/assignments/{id}/reminder', methods: ['POST'], format: 'json')]
    public function reminder(
        string $id,
        #[MapRequestPayload]
        ReminderPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->sendVolunteerReminderHandler)(new SendVolunteerReminderCommand($id, $p->channel, $p->reference)), 201);
    }
}
