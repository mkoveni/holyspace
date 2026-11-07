<?php

declare(strict_types=1);

namespace App\People\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Command\CreateRelationship\CreateRelationshipCommand;
use App\People\Application\Command\EndRelationship\EndRelationshipCommand;
use App\People\Presentation\Request\CreateRelationshipRequest;
use App\People\Presentation\Request\RevisionRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Relationships between people. Reading them is done per person through /api/people/{id}/relationships. */
#[IsGranted('people.manage')]
#[Route('/api/relationships')]
final readonly class RelationshipController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateRelationshipRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateRelationshipCommand(
            $this->account->requireAccount(),
            Json::uuid($request->sourceId ?? throw new BadRequestException('Source is required.')),
            Json::uuid($request->targetId ?? throw new BadRequestException('Target is required.')),
            $request->type ?? throw new BadRequestException('Relationship type is required.'),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/{id}/end', methods: ['POST'])]
    public function end(#[PathUuid] string $id, #[MapRequestPayload] RevisionRequest $request): Response
    {
        $this->commands->dispatch(new EndRelationshipCommand($this->account->requireAccount(), $id, $request->expectedVersion));

        return new Response(status: 204);
    }
}
