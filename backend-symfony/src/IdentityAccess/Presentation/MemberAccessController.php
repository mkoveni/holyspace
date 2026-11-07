<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\Command\VerifyMemberLink\VerifyMemberLinkCommand;
use App\IdentityAccess\Application\MemberAccessEntry;
use App\IdentityAccess\Application\Query\ListMemberAccess\ListMemberAccessHandler;
use App\IdentityAccess\Application\Query\ListMemberAccess\ListMemberAccessQuery;
use App\IdentityAccess\Presentation\Request\MemberLinkRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Staff link login accounts to verified people. Needs identity.manage and people.manage together. */
#[IsGranted('identity.manage')]
#[IsGranted('people.manage')]
#[Route('/api/member-access')]
final readonly class MemberAccessController
{
    public function __construct(
        private Caller $caller,
        private CommandBus $commands,
        private ListMemberAccessHandler $list,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListMemberAccessQuery($limit, $offset));
        $response = new JsonResponse([
            'links' => array_map(static fn (MemberAccessEntry $entry): array => [
                'accountId' => $entry->accountId,
                'accountName' => $entry->accountName,
                'personId' => $entry->personId,
                'personName' => $entry->personName,
                'active' => $entry->active,
                'version' => $entry->version,
            ], $page->links),
            'hasMore' => $page->hasMore,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/{accountId}', methods: ['PUT'])]
    public function verify(#[PathUuid] string $accountId, #[MapRequestPayload] MemberLinkRequest $request): Response
    {
        $this->commands->dispatch(new VerifyMemberLinkCommand(
            $this->caller->accountId(),
            $accountId,
            Json::uuid($request->personId ?? throw new BadRequestException('Person is required.')),
            $request->active,
            $request->expectedVersion,
            $request->evidence,
        ));

        return new Response(status: 204);
    }
}
