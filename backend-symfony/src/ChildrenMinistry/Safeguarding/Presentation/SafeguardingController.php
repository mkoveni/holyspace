<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\Command\AuthorizePickup\AuthorizePickupCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RecordConsent\RecordConsentCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokeConsent\RevokeConsentCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokeGuardian\RevokeGuardianCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\RevokePickupAuthorization\RevokePickupAuthorizationCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\VerifyGuardian\VerifyGuardianCommand;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguarding\GetSafeguardingHandler;
use App\ChildrenMinistry\Safeguarding\Application\Query\GetSafeguarding\GetSafeguardingQuery;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\AuthorizePickupRequest;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\RecordConsentRequest;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\RevokeRequest;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\VerifyGuardianRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Guardian authority and pickup grants. Every route needs children.safeguarding. */
#[Route('/api/children')]
#[IsGranted('children.safeguarding')]
final readonly class SafeguardingController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetSafeguardingHandler $safeguarding,
    ) {
    }

    #[Route('/guardian-authorities', methods: ['POST'])]
    public function verifyGuardian(#[MapRequestPayload] VerifyGuardianRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new VerifyGuardianCommand(
            $this->account->requireAccount(),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            Json::uuid($request->guardianId ?? throw new BadRequestException('Guardian is required.')),
            Json::parseInstant($request->validFrom ?? throw new BadRequestException('Start is required.')),
            Json::parseInstant($request->validUntil ?? throw new BadRequestException('End is required.')),
            $request->evidenceReference,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/guardian-authorities/revoke', methods: ['POST'])]
    public function revokeGuardian(#[MapRequestPayload] RevokeRequest $request): Response
    {
        $this->commands->dispatch(new RevokeGuardianCommand(
            $this->account->requireAccount(),
            Json::uuid($request->id ?? throw new BadRequestException('Id is required.')),
            $request->expectedVersion,
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[Route('/consents', methods: ['POST'])]
    public function recordConsent(#[MapRequestPayload] RecordConsentRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new RecordConsentCommand(
            $this->account->requireAccount(),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            Json::uuid($request->programmeId ?? throw new BadRequestException('Programme is required.')),
            Json::uuid($request->policyId ?? throw new BadRequestException('Policy is required.')),
            Json::uuid($request->authorityId ?? throw new BadRequestException('Authority is required.')),
            Json::parseInstant($request->validFrom ?? throw new BadRequestException('Start is required.')),
            Json::parseInstant($request->validUntil ?? throw new BadRequestException('End is required.')),
            Json::parseInstant($request->acceptedAt ?? throw new BadRequestException('Acceptance time is required.')),
            $request->evidenceReference,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/consents/revoke', methods: ['POST'])]
    public function revokeConsent(#[MapRequestPayload] RevokeRequest $request): Response
    {
        $this->commands->dispatch(new RevokeConsentCommand(
            $this->account->requireAccount(),
            Json::uuid($request->id ?? throw new BadRequestException('Id is required.')),
            $request->expectedVersion,
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[Route('/pickup-authorizations', methods: ['POST'])]
    public function authorizePickup(#[MapRequestPayload] AuthorizePickupRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new AuthorizePickupCommand(
            $this->account->requireAccount(),
            Json::uuid($request->programmeId ?? throw new BadRequestException('Programme is required.')),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            Json::uuid($request->collectorId ?? throw new BadRequestException('Collector is required.')),
            Json::uuid($request->authorityId ?? throw new BadRequestException('Authority is required.')),
            Json::parseInstant($request->validFrom ?? throw new BadRequestException('Start is required.')),
            Json::parseInstant($request->validUntil ?? throw new BadRequestException('End is required.')),
            $request->evidenceReference,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/pickup-authorizations/revoke', methods: ['POST'])]
    public function revokePickupAuthorization(#[MapRequestPayload] RevokeRequest $request): Response
    {
        $this->commands->dispatch(new RevokePickupAuthorizationCommand(
            $this->account->requireAccount(),
            Json::uuid($request->id ?? throw new BadRequestException('Id is required.')),
            $request->expectedVersion,
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[Route('/safeguarding/{childId}', methods: ['GET'])]
    public function get(#[PathUuid] string $childId): JsonResponse
    {
        return new JsonResponse(SafeguardingJson::safeguarding($this->safeguarding->handle(new GetSafeguardingQuery($childId))));
    }
}
