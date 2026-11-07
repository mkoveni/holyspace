<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Command\CreateFund\CreateFundCommand;
use App\Giving\Application\Command\UpdateFund\UpdateFundCommand;
use App\Giving\Application\Contract\FundView;
use App\Giving\Application\Query\ListGivingFunds\ListGivingFundsHandler;
use App\Giving\Application\Query\ListGivingFunds\ListGivingFundsQuery;
use App\Giving\Presentation\Request\CreateFundRequest;
use App\Giving\Presentation\Request\UpdateFundRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Giving funds. Listing needs giving.read (which giving.manage implies); writes need giving.manage. */
#[Route('/api/giving/funds')]
final readonly class GivingFundsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListGivingFundsHandler $list,
    ) {
    }

    #[IsGranted('giving.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, validationFailedStatusCode: 400)] bool $archived = false,
        #[MapQueryParameter] ?string $search = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListGivingFundsQuery($archived, $search, $limit, $offset));

        return new JsonResponse([
            'items' => array_map(static fn (FundView $fund): array => [
                'id' => $fund->id,
                'name' => $fund->name,
                'description' => $fund->description,
                'active' => $fund->active,
                'version' => $fund->version,
            ], $page->items),
            'total' => $page->total,
        ]);
    }

    #[IsGranted('giving.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateFundRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateFundCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $request->name,
            $request->description,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('giving.manage')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] UpdateFundRequest $request): Response
    {
        $this->commands->dispatch(new UpdateFundCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->name,
            $request->description,
            $request->active,
            $request->reason,
        ));

        return new Response(status: 204);
    }
}
