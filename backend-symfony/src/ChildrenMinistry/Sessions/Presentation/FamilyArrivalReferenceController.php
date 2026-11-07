<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Query\GetFamilyArrivalReference\GetFamilyArrivalReferenceHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetFamilyArrivalReference\GetFamilyArrivalReferenceQuery;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Station staff review a family's check-in QR. It carries identifiers, not authority, and this GET records nothing. */
#[Route('/api/children/sessions')]
#[IsGranted('children.station')]
final readonly class FamilyArrivalReferenceController
{
    public function __construct(private AuthenticatedAccount $account, private GetFamilyArrivalReferenceHandler $handler)
    {
    }

    #[Route('/{sessionId}/arrival-reference/{childId}', methods: ['GET'])]
    public function review(#[PathUuid] string $sessionId, #[PathUuid] string $childId): JsonResponse
    {
        $response = new JsonResponse(MemberFamilyJson::arrivalReview($this->handler->handle(new GetFamilyArrivalReferenceQuery($this->account->requireAccount(), $sessionId, $childId))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
