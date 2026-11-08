<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use App\Volunteering\Application\Command\ApproveServingRole\ApproveServingRoleCommand;
use App\Volunteering\Application\Command\CreateServingRole\CreateServingRoleCommand;
use App\Volunteering\Application\Command\CreateServingTeam\CreateServingTeamCommand;
use App\Volunteering\Application\Command\JoinServingTeam\JoinServingTeamCommand;
use App\Volunteering\Application\Command\RevokeServingApproval\RevokeServingApprovalCommand;
use App\Volunteering\Application\Command\UpdateServingRole\UpdateServingRoleCommand;
use App\Volunteering\Application\Command\UpdateServingTeam\UpdateServingTeamCommand;
use App\Volunteering\Application\Query\GetServingTeam\GetServingTeamHandler;
use App\Volunteering\Application\Query\GetServingTeam\GetServingTeamQuery;
use App\Volunteering\Application\Query\ListServingTeams\ListServingTeamsHandler;
use App\Volunteering\Application\Query\ListServingTeams\ListServingTeamsQuery;
use App\Volunteering\Presentation\Request\ApprovalRequest;
use App\Volunteering\Presentation\Request\MembershipRequest;
use App\Volunteering\Presentation\Request\RevokeRequest;
use App\Volunteering\Presentation\Request\RoleDraftRequest;
use App\Volunteering\Presentation\Request\RoleEditRequest;
use App\Volunteering\Presentation\Request\TeamDraftRequest;
use App\Volunteering\Presentation\Request\TeamEditRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Java `ServingTeamsController` (teams, roles, memberships, approvals, revocation) and `ServingRolesController` (role edits). */
final readonly class ServingTeamsController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListServingTeamsHandler $list,
        private GetServingTeamHandler $get,
    ) {
    }

    #[Route('/api/volunteering/teams', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function createTeam(#[MapRequestPayload] TeamDraftRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new CreateServingTeamCommand($this->account->requireAccount(), $request->name))], 201);
    }

    #[Route('/api/volunteering/teams/{id}', methods: ['PUT'])]
    #[IsGranted('volunteering.manage')]
    public function updateTeam(#[PathUuid] string $id, #[MapRequestPayload] TeamEditRequest $request): Response
    {
        $this->commands->dispatch(new UpdateServingTeamCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->name, $request->active));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/teams/{id}/roles', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function role(#[PathUuid] string $id, #[MapRequestPayload] RoleDraftRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new CreateServingRoleCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->name,
            $request->childrenRole,
            VolunteeringJson::uuids($request->requiredCredentials ?? []),
        ))], 201);
    }

    #[Route('/api/volunteering/teams/{teamId}/roles/{roleId}', methods: ['PUT'])]
    #[IsGranted('volunteering.manage')]
    public function editRole(#[PathUuid] string $teamId, #[PathUuid] string $roleId, #[MapRequestPayload] RoleEditRequest $request): Response
    {
        $this->commands->dispatch(new UpdateServingRoleCommand(
            $this->account->requireAccount(),
            $teamId,
            $roleId,
            $request->expectedVersion,
            $request->name,
            $request->childrenRole,
            $request->active,
            VolunteeringJson::uuids($request->requiredCredentials ?? []),
        ));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/teams/{id}/memberships', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function join(#[PathUuid] string $id, #[MapRequestPayload] MembershipRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new JoinServingTeamCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid((string) $request->volunteerId),
            Json::parseInstant((string) $request->validFrom),
            Json::parseInstant((string) $request->validUntil),
        ))], 201);
    }

    #[Route('/api/volunteering/teams/{id}/approvals', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function approve(#[PathUuid] string $id, #[MapRequestPayload] ApprovalRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new ApproveServingRoleCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid((string) $request->volunteerId),
            Json::uuid((string) $request->roleId),
            Json::parseInstant((string) $request->validFrom),
            Json::parseInstant((string) $request->validUntil),
        ))], 201);
    }

    #[Route('/api/volunteering/teams/{id}/revoke', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function revoke(#[PathUuid] string $id, #[MapRequestPayload] RevokeRequest $request): Response
    {
        $this->commands->dispatch(new RevokeServingApprovalCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid((string) $request->volunteerId),
            Json::uuid((string) $request->recordId),
            $request->roleApproval,
        ));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/teams', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function teams(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(VolunteeringJson::teams($this->list->handle(new ListServingTeamsQuery($limit, $offset))));
    }

    #[Route('/api/volunteering/teams/{id}', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function team(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(VolunteeringJson::details($this->get->handle(new GetServingTeamQuery($id))));
    }
}
