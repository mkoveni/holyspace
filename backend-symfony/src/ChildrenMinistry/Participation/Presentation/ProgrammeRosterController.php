<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation;

use App\ChildrenMinistry\Participation\Application\Query\ListProgrammeChildren\ListProgrammeChildrenHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListProgrammeChildren\ListProgrammeChildrenQuery;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Who is enrolled in a programme, with their current classes. Never exposes guardian, consent or care details. */
final readonly class ProgrammeRosterController
{
    public function __construct(private ListProgrammeChildrenHandler $roster)
    {
    }

    #[IsGranted('children.read')]
    #[Route('/api/children/programmes/{programmeId}/children', methods: ['GET'])]
    public function list(
        #[PathUuid] string $programmeId,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] string $status = 'ALL',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(ParticipationJson::roster($this->roster->handle(new ListProgrammeChildrenQuery($programmeId, $search, $status, $limit, $offset))));
    }
}
