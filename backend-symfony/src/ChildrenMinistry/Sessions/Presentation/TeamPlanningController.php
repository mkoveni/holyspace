<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\ApplyTeamPlan\ApplyTeamPlanCommand;
use App\ChildrenMinistry\Sessions\Application\Query\GetTeamPlanning\GetTeamPlanningHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetTeamPlanning\GetTeamPlanningQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListPlanningWorkers\ListPlanningWorkersHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListPlanningWorkers\ListPlanningWorkersQuery;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewTeamPlan\PreviewTeamPlanHandler;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewTeamPlan\PreviewTeamPlanQuery;
use App\ChildrenMinistry\Sessions\Presentation\Request\ApplyTeamPlanRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\TeamPlanDraftRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Coordinators plan upcoming session teams in bulk. Everything here needs children.manage. */
#[Route('/api/children/team-planning')]
#[IsGranted('children.manage')]
final readonly class TeamPlanningController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetTeamPlanningHandler $team,
        private ListPlanningWorkersHandler $workers,
        private PreviewTeamPlanHandler $preview,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function team(
        #[MapQueryParameter] ?string $from = null,
        #[MapQueryParameter] ?string $until = null,
        #[MapQueryParameter] ?string $programmeId = null,
        #[MapQueryParameter] ?string $locationId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] string $state = 'ALL',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $view = $this->team->handle(new GetTeamPlanningQuery(
            CalendarDate::parse($from ?? throw new BadRequestException('From is required.')),
            CalendarDate::parse($until ?? throw new BadRequestException('Until is required.')),
            null === $programmeId ? null : Json::uuid($programmeId),
            null === $locationId ? null : Json::uuid($locationId),
            $search,
            $state,
            $limit,
            $offset,
        ));

        return new JsonResponse(TeamPlanningJson::planning($view));
    }

    #[Route('/workers', methods: ['GET'])]
    public function workers(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(TeamPlanningJson::workers($this->workers->handle(new ListPlanningWorkersQuery($search, $limit, $offset))));
    }

    #[Route('/preview', methods: ['POST'])]
    public function preview(#[MapRequestPayload] TeamPlanDraftRequest $request): JsonResponse
    {
        return new JsonResponse(TeamPlanningJson::preview($this->preview->handle(new PreviewTeamPlanQuery($request->toDraft()))));
    }

    #[Route('/apply', methods: ['POST'])]
    public function apply(#[MapRequestPayload] ApplyTeamPlanRequest $request): Response
    {
        $this->commands->dispatch(new ApplyTeamPlanCommand(
            $this->account->requireAccount(),
            ($request->draft ?? throw new BadRequestException('Draft is required.'))->toDraft(),
            (string) $request->reason,
        ));

        return new Response(status: 204);
    }
}
