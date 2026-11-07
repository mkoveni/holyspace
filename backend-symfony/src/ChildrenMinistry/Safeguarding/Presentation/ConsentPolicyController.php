<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\Command\PublishConsentPolicy\PublishConsentPolicyCommand;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetConsentPolicy\GetConsentPolicyHandler;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetConsentPolicy\GetConsentPolicyQuery;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\PublishConsentPolicyRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Consent policy versions. Publishing needs children.manage, reading children.read. */
#[Route('/api/children/consent-policies')]
final readonly class ConsentPolicyController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetConsentPolicyHandler $policies,
    ) {
    }

    #[IsGranted('children.manage')]
    #[Route('', methods: ['POST'])]
    public function publish(#[MapRequestPayload] PublishConsentPolicyRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new PublishConsentPolicyCommand(
            $this->account->requireAccount(),
            Json::uuid($request->programmeId ?? throw new BadRequestException('Programme is required.')),
            $request->expectedVersion,
            $request->label,
            $request->documentReference,
            $request->contentHash,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.read')]
    #[Route('/{policyId}', methods: ['GET'])]
    public function get(#[PathUuid] string $policyId): JsonResponse
    {
        return new JsonResponse(SafeguardingJson::policy($this->policies->handle(new GetConsentPolicyQuery($policyId))));
    }
}
