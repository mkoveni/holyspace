<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Command\SetOnlineFund\SetOnlineFundCommand;
use App\Giving\Application\Command\VerifyDonorLink\VerifyDonorLinkCommand;
use App\Giving\Application\Contract\DonorAccessLink;
use App\Giving\Application\Contract\OnlineFund;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Giving\Application\Query\ListDonorAccess\ListDonorAccessHandler;
use App\Giving\Application\Query\ListDonorAccess\ListDonorAccessQuery;
use App\Giving\Presentation\Request\SetOnlineFundRequest;
use App\Giving\Presentation\Request\VerifyDonorLinkRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verified donor links and opt-in online funds. Account links need identity.manage and giving.manage; online funds need
 * giving.manage. The donor-access view is never cached.
 */
#[Route('/api/giving')]
final readonly class GivingDonorAccessController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListDonorAccessHandler $list,
        private OnlineFundDirectory $funds,
    ) {
    }

    #[IsGranted('identity.manage')]
    #[IsGranted('giving.manage')]
    #[Route('/donor-access', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListDonorAccessQuery($limit, $offset));
        $response = new JsonResponse([
            'links' => array_map(static fn (DonorAccessLink $link): array => [
                'accountId' => $link->accountId,
                'accountName' => $link->accountName,
                'personId' => $link->personId,
                'personName' => $link->personName,
                'active' => $link->active,
                'version' => $link->version,
            ], $page->links),
            'funds' => array_map(static fn (OnlineFund $fund): array => [
                'id' => $fund->id,
                'name' => $fund->name,
                'description' => $fund->description,
                'active' => $fund->active,
                'enabled' => $fund->enabled,
                'version' => $fund->version,
            ], $page->funds),
            'hasMore' => $page->hasMore,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[IsGranted('identity.manage')]
    #[IsGranted('giving.manage')]
    #[Route('/donor-access/{accountId}', methods: ['PUT'])]
    public function link(#[PathUuid] string $accountId, #[MapRequestPayload] VerifyDonorLinkRequest $request): Response
    {
        $this->commands->dispatch(new VerifyDonorLinkCommand(
            $this->account->requireAccount(),
            $accountId,
            strtolower((string) $request->personId),
            $request->active,
            $request->expectedVersion,
            $request->evidence,
        ));

        return new Response(null, 204);
    }

    /** The online-giving catalogue on its own, for staff who manage giving but not accounts. */
    #[IsGranted('giving.manage')]
    #[Route('/online-funds', methods: ['GET'])]
    public function funds(): JsonResponse
    {
        $entries = $this->funds->funds(true);
        $response = new JsonResponse([
            'funds' => array_map(static fn (OnlineFund $fund): array => [
                'id' => $fund->id,
                'name' => $fund->name,
                'description' => $fund->description,
                'active' => $fund->active,
                'enabled' => $fund->enabled,
                'version' => $fund->version,
            ], \array_slice($entries, 0, OnlineFundDirectory::CATALOGUE_LIMIT)),
            'truncated' => \count($entries) > OnlineFundDirectory::CATALOGUE_LIMIT,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[IsGranted('giving.manage')]
    #[Route('/online-funds/{fundId}', methods: ['PUT'])]
    public function fund(#[PathUuid] string $fundId, #[MapRequestPayload] SetOnlineFundRequest $request): Response
    {
        $this->commands->dispatch(new SetOnlineFundCommand($this->account->requireAccount(), $fundId, $request->enabled, $request->expectedVersion));

        return new Response(null, 204);
    }
}
