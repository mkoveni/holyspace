<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\RegisterWorker\RegisterWorkerCommand;
use App\ChildrenMinistry\Sessions\Application\Command\ReviewWorker\ReviewWorkerCommand;
use App\ChildrenMinistry\Sessions\Application\Query\GetWorker\GetWorkerHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetWorker\GetWorkerQuery;
use App\ChildrenMinistry\Sessions\Presentation\Request\RegisterWorkerRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\ReviewWorkerRequest;
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

/** Who is cleared to serve with children. Every route needs children.safeguarding. */
#[Route('/api/children/workers')]
#[IsGranted('children.safeguarding')]
final readonly class WorkerController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetWorkerHandler $workers,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterWorkerRequest $request): Response
    {
        $this->commands->dispatch(new RegisterWorkerCommand(
            $this->account->requireAccount(),
            Json::uuid($request->personId ?? throw new BadRequestException('Person is required.')),
            Json::uuid($request->accountId ?? throw new BadRequestException('Account is required.')),
            Json::parseInstant($request->screenedUntil ?? throw new BadRequestException('Screening end is required.')),
            Json::parseInstant($request->trainedUntil ?? throw new BadRequestException('Training end is required.')),
            $request->evidenceReference,
        ));

        return new Response(status: 204);
    }

    #[Route('', methods: ['PUT'])]
    public function review(#[MapRequestPayload] ReviewWorkerRequest $request): Response
    {
        $this->commands->dispatch(new ReviewWorkerCommand(
            $this->account->requireAccount(),
            Json::uuid($request->personId ?? throw new BadRequestException('Person is required.')),
            $request->expectedVersion,
            Json::parseInstant($request->screenedUntil ?? throw new BadRequestException('Screening end is required.')),
            Json::parseInstant($request->trainedUntil ?? throw new BadRequestException('Training end is required.')),
            $request->evidenceReference,
            $request->revoked,
        ));

        return new Response(status: 204);
    }

    #[Route('/{personId}', methods: ['GET'])]
    public function get(#[PathUuid] string $personId): JsonResponse
    {
        $worker = $this->workers->handle(new GetWorkerQuery($personId));

        return new JsonResponse([
            'personId' => $worker->personId,
            'accountId' => $worker->accountId,
            'verifiedFrom' => Json::instant($worker->verifiedFrom),
            'screenedUntil' => Json::instant($worker->screenedUntil),
            'trainedUntil' => Json::instant($worker->trainedUntil),
            'evidenceReference' => $worker->evidenceReference,
            'verifiedBy' => $worker->verifiedBy,
            'revokedAt' => null === $worker->revokedAt ? null : Json::instant($worker->revokedAt),
            'version' => $worker->version,
        ]);
    }
}
