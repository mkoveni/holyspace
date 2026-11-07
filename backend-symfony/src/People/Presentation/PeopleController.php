<?php

declare(strict_types=1);

namespace App\People\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Command\AddAddress\AddAddressCommand;
use App\People\Application\Command\AddContact\AddContactCommand;
use App\People\Application\Command\CreateTitle\CreateTitleCommand;
use App\People\Application\Command\EndAddress\EndAddressCommand;
use App\People\Application\Command\EndContact\EndContactCommand;
use App\People\Application\Command\RegisterPerson\RegisterPersonCommand;
use App\People\Application\Command\SetPersonArchived\SetPersonArchivedCommand;
use App\People\Application\Command\UpdatePerson\UpdatePersonCommand;
use App\People\Application\MembershipView;
use App\People\Application\PersonSummary;
use App\People\Application\Query\GetPeopleMetadata\GetPeopleMetadataHandler;
use App\People\Application\Query\GetPeopleMetadata\GetPeopleMetadataQuery;
use App\People\Application\Query\GetPerson\GetPersonHandler;
use App\People\Application\Query\GetPerson\GetPersonQuery;
use App\People\Application\Query\GetPersonHouseholds\GetPersonHouseholdsHandler;
use App\People\Application\Query\GetPersonHouseholds\GetPersonHouseholdsQuery;
use App\People\Application\Query\GetPersonRelationships\GetPersonRelationshipsHandler;
use App\People\Application\Query\GetPersonRelationships\GetPersonRelationshipsQuery;
use App\People\Application\Query\SearchPeople\SearchPeopleHandler;
use App\People\Application\Query\SearchPeople\SearchPeopleQuery;
use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Presentation\Request\AddressRequest;
use App\People\Presentation\Request\ArchiveRequest;
use App\People\Presentation\Request\ContactRequest;
use App\People\Presentation\Request\RegisterPersonRequest;
use App\People\Presentation\Request\RevisionRequest;
use App\People\Presentation\Request\TitleRequest;
use App\People\Presentation\Request\UpdatePersonRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** People, their contact methods and addresses. Reads need people.read, writes people.manage. */
#[Route('/api/people')]
final readonly class PeopleController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private SearchPeopleHandler $search,
        private GetPersonHandler $get,
        private GetPeopleMetadataHandler $metadata,
        private GetPersonHouseholdsHandler $households,
        private GetPersonRelationshipsHandler $relationships,
    ) {
    }

    #[IsGranted('people.read')]
    #[Route('', methods: ['GET'])]
    public function search(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] bool $includeArchived = false,
        #[MapQueryParameter(validationFailedStatusCode: 400)] ?MembershipType $membershipType = null,
        #[MapQueryParameter(validationFailedStatusCode: 400)] ?AgeCategory $ageCategory = null,
        #[MapQueryParameter] ?string $asOf = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 1, 'max_range' => 100], validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 0, 'max_range' => 1000000], validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $people = $this->search->handle(new SearchPeopleQuery(
            $search,
            $includeArchived,
            $membershipType,
            $ageCategory,
            self::date($asOf),
            $limit,
            $offset,
        ));

        return new JsonResponse(array_map(static fn (PersonSummary $person): array => PeopleJson::summary($person), $people));
    }

    #[IsGranted('people.read')]
    #[Route('/metadata', methods: ['GET'])]
    public function metadata(): JsonResponse
    {
        return new JsonResponse(PeopleJson::metadata($this->metadata->handle(new GetPeopleMetadataQuery())));
    }

    #[IsGranted('people.manage')]
    #[Route('/titles', methods: ['POST'])]
    public function title(#[MapRequestPayload] TitleRequest $request): Response
    {
        $this->commands->dispatch(new CreateTitleCommand($this->account->requireAccount(), $request->code, $request->label));

        return new Response(status: 204);
    }

    #[IsGranted('people.read')]
    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id, #[MapQueryParameter] ?string $asOf = null): JsonResponse
    {
        return new JsonResponse(PeopleJson::person($this->get->handle(new GetPersonQuery($id, self::date($asOf)))));
    }

    #[IsGranted('people.manage')]
    #[Route('', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterPersonRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new RegisterPersonCommand(
            $this->account->requireAccount(),
            ($request->person ?? throw new BadRequestException('Person is required.'))->details(),
            $request->membershipType,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] UpdatePersonRequest $request): Response
    {
        $this->commands->dispatch(new UpdatePersonCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            ($request->person ?? throw new BadRequestException('Person is required.'))->details(),
            $request->membershipType ?? throw new BadRequestException('Membership type is required.'),
        ));

        return new Response(status: 204);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/archive', methods: ['PUT'])]
    public function archive(#[PathUuid] string $id, #[MapRequestPayload] ArchiveRequest $request): Response
    {
        $this->commands->dispatch(new SetPersonArchivedCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->archived));

        return new Response(status: 204);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/contacts', methods: ['POST'])]
    public function contact(#[PathUuid] string $id, #[MapRequestPayload] ContactRequest $request): JsonResponse
    {
        $contactId = $this->commands->dispatch(new AddContactCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->kind ?? throw new BadRequestException('Contact kind is required.'),
            $request->value,
            $request->primary,
        ));

        return new JsonResponse(['id' => $contactId], 201);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/contacts/{contactId}/end', methods: ['POST'])]
    public function endContact(#[PathUuid] string $id, #[PathUuid] string $contactId, #[MapRequestPayload] RevisionRequest $request): Response
    {
        $this->commands->dispatch(new EndContactCommand($this->account->requireAccount(), $id, $request->expectedVersion, $contactId));

        return new Response(status: 204);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/addresses', methods: ['POST'])]
    public function address(#[PathUuid] string $id, #[MapRequestPayload] AddressRequest $request): JsonResponse
    {
        $addressId = $this->commands->dispatch(new AddAddressCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->line1,
            $request->line2,
            $request->city,
            $request->region,
            $request->postalCode,
            $request->countryCode,
        ));

        return new JsonResponse(['id' => $addressId], 201);
    }

    #[IsGranted('people.manage')]
    #[Route('/{id}/addresses/{addressId}/end', methods: ['POST'])]
    public function endAddress(#[PathUuid] string $id, #[PathUuid] string $addressId, #[MapRequestPayload] RevisionRequest $request): Response
    {
        $this->commands->dispatch(new EndAddressCommand($this->account->requireAccount(), $id, $request->expectedVersion, $addressId));

        return new Response(status: 204);
    }

    #[IsGranted('people.read')]
    #[Route('/{id}/households', methods: ['GET'])]
    public function households(#[PathUuid] string $id): JsonResponse
    {
        $memberships = $this->households->handle(new GetPersonHouseholdsQuery($id));

        return new JsonResponse(array_map(static fn (MembershipView $membership): array => PeopleJson::membership($membership), $memberships));
    }

    #[IsGranted('people.read')]
    #[Route('/{id}/relationships', methods: ['GET'])]
    public function relationships(#[PathUuid] string $id): JsonResponse
    {
        $links = $this->relationships->handle(new GetPersonRelationshipsQuery($id));

        return new JsonResponse(array_map(PeopleJson::relationship(...), $links));
    }

    private static function date(?string $value): ?CalendarDate
    {
        return null === $value || '' === $value ? null : CalendarDate::parse($value);
    }
}
