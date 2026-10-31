<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\EnableMemberAccess\EnableMemberAccessCommand;
use App\IdentityAccess\Application\Command\EnableMemberAccess\EnableMemberAccessHandler;
use App\IdentityAccess\Application\ReadModel\AccessCatalogue;
use App\IdentityAccess\Presentation\Http\DTO\AccountDirectoryQuery;
use App\IdentityAccess\Presentation\Http\DTO\MemberAccessPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('identity.manage')]
final class MemberAccessController extends WorkflowController
{
    public function __construct(private readonly EnableMemberAccessHandler $enableMemberAccessHandler)
    {
    }

    #[Route('/api/users', methods: ['GET'], format: 'json')]
    public function accounts(
        AccessCatalogue $catalogue,
        #[MapQueryString(validationFailedStatusCode: 422)]
        AccountDirectoryQuery $q = new AccountDirectoryQuery(),
    ): JsonResponse {
        return $this->result(fn () => ['items' => $catalogue->accounts($q->search, $q->limit)]);
    }

    #[Route('/api/users/{id}/member-access', methods: ['POST'], format: 'json')]
    public function enable(
        string $id,
        #[MapRequestPayload]
        MemberAccessPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->enableMemberAccessHandler)(new EnableMemberAccessCommand($id, $p->personId, $p->expectedPersonId)));
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        $response = parent::result($operation, $status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
