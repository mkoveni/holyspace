<?php

declare(strict_types=1);

namespace App\People\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Command\AddHouseholdMember\AddHouseholdMemberCommand;
use App\People\Application\Command\CreateHousehold\CreateHouseholdCommand;
use App\People\Application\Command\EndHouseholdMembership\EndHouseholdMembershipCommand;
use App\People\Application\Command\SetHouseholdContact\SetHouseholdContactCommand;
use App\People\Application\Command\UpdateHousehold\UpdateHouseholdCommand;
use App\People\Application\HouseholdSummary;
use App\People\Application\Query\GetHousehold\GetHouseholdHandler;
use App\People\Application\Query\GetHousehold\GetHouseholdQuery;
use App\People\Application\Query\SearchHouseholds\SearchHouseholdsHandler;
use App\People\Application\Query\SearchHouseholds\SearchHouseholdsQuery;
use App\People\Presentation\Request\AddMemberRequest;
use App\People\Presentation\Request\CreateHouseholdRequest;
use App\People\Presentation\Request\PrimaryContactRequest;
use App\People\Presentation\Request\RenameHouseholdRequest;
use App\People\Presentation\Request\RevisionRequest;
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

/** Households and their dated memberships. Reads need people.read, writes people.manage. */
#[Route('/api/households')]
final readonly class HouseholdController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private SearchHouseholdsHandler $search,
        private GetHouseholdHandler $get,
    ) {
    }

    #[IsGranted('people.read')]
    #[Route('', methods: ['GET'])]
    public function search(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 1, 'max_range' => 100], validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 0, 'max_range' => 1000000], validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $households = $this->search->handle(new SearchHouseholdsQuery($search, $limit, $offset));

        return new JsonResponse(array_map(static fn (HouseholdSummary $household): array => PeopleJson::household($household), $households));
    }

    #[IsGranted('people.read')]
    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(PeopleJson::householdView($this->get->handle(new GetHouseholdQuery($id))));
    }

    #[IsGranted('people.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateHouseholdRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateHouseholdCommand($this->account->requireAccount(), $request->name));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] RenameHouseholdRequest $request): Response
    {
        $this->commands->dispatch(new UpdateHouseholdCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->name));

        return new Response(status: 204);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/members', methods: ['POST'])]
    public function member(#[PathUuid] string $id, #[MapRequestPayload] AddMemberRequest $request): JsonResponse
    {
        $membershipId = $this->commands->dispatch(new AddHouseholdMemberCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid($request->personId ?? throw new BadRequestException('Person is required.')),
            $request->primary,
        ));

        return new JsonResponse(['id' => $membershipId], 201);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/members/{membershipId}/end', methods: ['POST'])]
    public function end(#[PathUuid] string $id, #[PathUuid] string $membershipId, #[MapRequestPayload] RevisionRequest $request): Response
    {
        $this->commands->dispatch(new EndHouseholdMembershipCommand($this->account->requireAccount(), $id, $request->expectedVersion, $membershipId));

        return new Response(status: 204);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/primary-contact', methods: ['PUT'])]
    public function primaryContact(#[PathUuid] string $id, #[MapRequestPayload] PrimaryContactRequest $request): Response
    {
        $this->commands->dispatch(new SetHouseholdContactCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            null === $request->membershipId ? null : Json::uuid($request->membershipId),
        ));

        return new Response(status: 204);
    }
}
