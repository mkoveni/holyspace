<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpIssue;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpView;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguardingFollowUp\GetSafeguardingFollowUpHandler;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguardingFollowUp\GetSafeguardingFollowUpQuery;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The safeguarding worklist. Dates and states only; the coordinator opens each record through its own, audited use case. */
#[Route('/api/children/safeguarding-follow-up')]
#[IsGranted('children.safeguarding')]
final readonly class SafeguardingFollowUpController
{
    public function __construct(private GetSafeguardingFollowUpHandler $handler)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $programmeId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] string $kind = 'ALL',
        #[MapQueryParameter] string $state = 'ALL',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $days = 30,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $view = $this->handler->handle(new GetSafeguardingFollowUpQuery(null === $programmeId ? null : Json::uuid($programmeId), $search, $kind, $state, $days, $limit, $offset));

        return new JsonResponse(self::json($view));
    }

    /** @return array<string, mixed> */
    public static function json(FollowUpView $view): array
    {
        return [
            'issues' => array_map(static fn (FollowUpIssue $i): array => [
                'id' => $i->id,
                'kind' => $i->kind,
                'state' => $i->state,
                'childId' => $i->childId,
                'childName' => $i->childName,
                'programmeId' => $i->programmeId,
                'programme' => $i->programme,
                'expiresAt' => null === $i->expiresAt ? null : Json::instant($i->expiresAt),
                'reviewOn' => $i->reviewOn?->iso(),
                'action' => $i->action,
                'message' => $i->message,
            ], $view->issues),
            'total' => $view->total,
            'summary' => ['children' => $view->summary->children, 'missing' => $view->summary->missing, 'expired' => $view->summary->expired, 'expiring' => $view->summary->expiring, 'careDue' => $view->summary->careDue],
            'careIncluded' => $view->careIncluded,
            'through' => $view->through->iso(),
            'timeZone' => $view->timeZone,
            'checkedAt' => Json::instant($view->checkedAt),
        ];
    }
}
