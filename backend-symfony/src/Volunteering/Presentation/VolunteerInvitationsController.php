<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use App\Volunteering\Application\Command\ExpireVolunteerInvitations\ExpireVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\InviteVolunteer\InviteVolunteerCommand;
use App\Volunteering\Application\Command\RespondVolunteerInvitation\RespondVolunteerInvitationCommand;
use App\Volunteering\Application\Query\GetOwnVolunteerSchedule\GetOwnVolunteerScheduleHandler;
use App\Volunteering\Application\Query\GetOwnVolunteerSchedule\GetOwnVolunteerScheduleQuery;
use App\Volunteering\Presentation\Request\ExpireRequest;
use App\Volunteering\Presentation\Request\InviteRequest;
use App\Volunteering\Presentation\Request\RespondRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Java `VolunteerInvitationsController`: invitations, answers, expiry and the volunteer's own invitations. */
final readonly class VolunteerInvitationsController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetOwnVolunteerScheduleHandler $own,
    ) {
    }

    #[Route('/api/volunteering/assignments/{id}/invitations', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function invite(#[PathUuid] string $id, #[MapRequestPayload] InviteRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new InviteVolunteerCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::parseInstant((string) $request->expiresAt),
        ))], 201);
    }

    #[Route('/api/volunteering/invitations/{id}/respond', methods: ['POST'])]
    #[IsGranted('volunteering.serve')]
    public function respond(#[PathUuid] string $id, #[MapRequestPayload] RespondRequest $request): Response
    {
        $this->commands->dispatch(new RespondVolunteerInvitationCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->decision));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/invitations/expire', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function expire(#[MapRequestPayload] ExpireRequest $request): JsonResponse
    {
        return new JsonResponse($this->commands->dispatch(new ExpireVolunteerInvitationsCommand($this->account->requireAccount(), $request->limit)));
    }

    #[Route('/api/volunteering/me/invitations', methods: ['GET'])]
    #[IsGranted('volunteering.serve')]
    public function own(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(VolunteeringJson::invitationDetails(...), $this->own->handle(new GetOwnVolunteerScheduleQuery($this->account->requireAccount(), $limit, $offset))));
    }
}
