<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\Query\GetChildReadiness\GetChildReadinessHandler;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetChildReadiness\GetChildReadinessQuery;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessStep;
use App\ChildrenMinistry\Safeguarding\Application\Readiness\ReadinessView;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Programme preparation of one child: completion flags only, never care or guardian evidence. */
#[Route('/api/children/programmes/{programmeId}/children/{childId}/readiness', methods: ['GET'])]
final readonly class ChildReadinessController
{
    public function __construct(private GetChildReadinessHandler $readiness)
    {
    }

    #[IsGranted('children.read')]
    public function __invoke(#[PathUuid] string $programmeId, #[PathUuid] string $childId): JsonResponse
    {
        return new JsonResponse(self::json($this->readiness->handle(new GetChildReadinessQuery($programmeId, $childId))));
    }

    /** @return array<string, mixed> */
    private static function json(ReadinessView $view): array
    {
        $enrolment = $view->enrolment;

        return [
            'ready' => $view->ready,
            'completed' => $view->completed,
            'total' => $view->total,
            'on' => $view->on->iso(),
            'checkedAt' => Json::instant($view->checkedAt),
            'steps' => array_map(static fn (ReadinessStep $step): array => ['code' => $step->code, 'complete' => $step->complete], $view->steps),
            'enrolment' => null === $enrolment ? null : [
                'id' => $enrolment->id,
                'programmeId' => $enrolment->programmeId,
                'childId' => $enrolment->childId,
                'validFrom' => $enrolment->validFrom->iso(),
                'validUntil' => $enrolment->validUntil->iso(),
                'endedAt' => null === $enrolment->endedAt ? null : Json::instant($enrolment->endedAt),
                'version' => $enrolment->version,
                'createdAt' => Json::instant($enrolment->createdAt),
            ],
            'timeZone' => $view->timeZone,
        ];
    }
}
