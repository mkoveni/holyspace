<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation;

use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\Command\CreateClass\CreateClassCommand;
use App\ChildrenMinistry\Participation\Application\Command\CreateProgramme\CreateProgrammeCommand;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\ChildrenMinistry\Participation\Application\Query\GetProgramme\GetProgrammeHandler;
use App\ChildrenMinistry\Participation\Application\Query\GetProgramme\GetProgrammeQuery;
use App\ChildrenMinistry\Participation\Application\Query\ListClasses\ListClassesHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListClasses\ListClassesQuery;
use App\ChildrenMinistry\Participation\Application\Query\ListProgrammes\ListProgrammesHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListProgrammes\ListProgrammesQuery;
use App\ChildrenMinistry\Participation\Presentation\Request\CreateClassRequest;
use App\ChildrenMinistry\Participation\Presentation\Request\CreateProgrammeRequest;
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

/** Programmes and their age-group classes. Reads need children.read, writes children.manage. */
#[Route('/api/children')]
final readonly class ProgrammeController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListProgrammesHandler $programmes,
        private GetProgrammeHandler $programme,
        private ListClassesHandler $classes,
    ) {
    }

    #[IsGranted('children.manage')]
    #[Route('/programmes', methods: ['POST'])]
    public function createProgramme(#[MapRequestPayload] CreateProgrammeRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateProgrammeCommand($this->account->requireAccount(), $request->name, $request->minStaff, $request->childrenPerStaff));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.read')]
    #[Route('/programmes', methods: ['GET'])]
    public function listProgrammes(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $programmes = $this->programmes->handle(new ListProgrammesQuery($search, $limit, $offset));

        return new JsonResponse(array_map(static fn (ProgrammeView $programme): array => ParticipationJson::programme($programme), $programmes));
    }

    #[IsGranted('children.read')]
    #[Route('/programmes/{programmeId}', methods: ['GET'])]
    public function getProgramme(#[PathUuid] string $programmeId): JsonResponse
    {
        return new JsonResponse(ParticipationJson::programme($this->programme->handle(new GetProgrammeQuery($programmeId))));
    }

    #[IsGranted('children.manage')]
    #[Route('/classes', methods: ['POST'])]
    public function createClass(#[MapRequestPayload] CreateClassRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateClassCommand(
            $this->account->requireAccount(),
            Json::uuid($request->programmeId ?? throw new BadRequestException('Programme is required.')),
            $request->name,
            $request->minAgeMonths,
            $request->maxAgeMonths,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.read')]
    #[Route('/classes', methods: ['GET'])]
    public function listClasses(#[MapQueryParameter(validationFailedStatusCode: 400)] string $programmeId): JsonResponse
    {
        $classes = $this->classes->handle(new ListClassesQuery(Json::uuid($programmeId)));

        return new JsonResponse(array_map(static fn (ClassView $class): array => ParticipationJson::ministryClass($class), $classes));
    }
}
