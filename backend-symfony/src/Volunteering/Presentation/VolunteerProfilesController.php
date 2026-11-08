<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use App\Volunteering\Application\Command\ChangeVolunteerStatus\ChangeVolunteerStatusCommand;
use App\Volunteering\Application\Command\CreateCredentialType\CreateCredentialTypeCommand;
use App\Volunteering\Application\Command\LinkVolunteerAccount\LinkVolunteerAccountCommand;
use App\Volunteering\Application\Command\RecordVolunteerCredential\RecordVolunteerCredentialCommand;
use App\Volunteering\Application\Command\RegisterVolunteer\RegisterVolunteerCommand;
use App\Volunteering\Application\Command\RevokeVolunteerCredential\RevokeVolunteerCredentialCommand;
use App\Volunteering\Application\Command\SetVolunteerAvailability\SetVolunteerAvailabilityCommand;
use App\Volunteering\Application\Query\GetVolunteer\GetVolunteerHandler;
use App\Volunteering\Application\Query\GetVolunteer\GetVolunteerQuery;
use App\Volunteering\Application\Query\ListCredentialTypes\ListCredentialTypesHandler;
use App\Volunteering\Application\Query\ListCredentialTypes\ListCredentialTypesQuery;
use App\Volunteering\Application\Query\ListVolunteers\ListVolunteersHandler;
use App\Volunteering\Application\Query\ListVolunteers\ListVolunteersQuery;
use App\Volunteering\Presentation\Request\AccountLinkRequest;
use App\Volunteering\Presentation\Request\CredentialRequest;
use App\Volunteering\Presentation\Request\CredentialRevokeRequest;
use App\Volunteering\Presentation\Request\CredentialTypeRequest;
use App\Volunteering\Presentation\Request\RegisterRequest;
use App\Volunteering\Presentation\Request\StatusRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Java `VolunteerProfilesController` and `VolunteerAccountController`: profiles, account links, credentials, availability. */
final readonly class VolunteerProfilesController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListVolunteersHandler $list,
        private GetVolunteerHandler $get,
        private ListCredentialTypesHandler $types,
    ) {
    }

    #[Route('/api/volunteering/volunteers', methods: ['POST'])]
    #[IsGranted('volunteering.manage')]
    public function register(#[MapRequestPayload] RegisterRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new RegisterVolunteerCommand(
            $this->account->requireAccount(),
            null === $request->personId ? null : Json::uuid($request->personId),
            null === $request->accountId ? null : Json::uuid($request->accountId),
        ))], 201);
    }

    #[Route('/api/volunteering/volunteers/{id}/status', methods: ['PUT'])]
    #[IsGranted('volunteering.manage')]
    public function status(#[PathUuid] string $id, #[MapRequestPayload] StatusRequest $request): Response
    {
        $this->commands->dispatch(new ChangeVolunteerStatusCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->status));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/volunteers/{id}/account', methods: ['PUT'])]
    #[IsGranted('volunteering.manage')]
    public function link(#[PathUuid] string $id, #[MapRequestPayload] AccountLinkRequest $request): Response
    {
        $this->commands->dispatch(new LinkVolunteerAccountCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            null === $request->accountId ? null : Json::uuid($request->accountId),
        ));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/credential-types', methods: ['POST'])]
    #[IsGranted('volunteering.credentials.manage')]
    public function type(#[MapRequestPayload] CredentialTypeRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new CreateCredentialTypeCommand($this->account->requireAccount(), $request->code, $request->name))], 201);
    }

    #[Route('/api/volunteering/volunteers/{id}/credentials', methods: ['POST'])]
    #[IsGranted('volunteering.credentials.manage')]
    public function credential(#[PathUuid] string $id, #[MapRequestPayload] CredentialRequest $request): JsonResponse
    {
        return new JsonResponse(['id' => $this->commands->dispatch(new RecordVolunteerCredentialCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid((string) $request->typeId),
            Json::parseInstant((string) $request->validFrom),
            Json::parseInstant((string) $request->validUntil),
            $request->evidence,
        ))], 201);
    }

    #[Route('/api/volunteering/volunteers/{id}/credentials/revoke', methods: ['POST'])]
    #[IsGranted('volunteering.credentials.manage')]
    public function revokeCredential(#[PathUuid] string $id, #[MapRequestPayload] CredentialRevokeRequest $request): Response
    {
        $this->commands->dispatch(new RevokeVolunteerCredentialCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid((string) $request->credentialId),
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/volunteers/{id}/availability', methods: ['PUT'])]
    #[IsGranted('volunteering.manage')]
    public function availability(#[PathUuid] string $id, Request $request): Response
    {
        $body = $request->toArray();
        $this->commands->dispatch(new SetVolunteerAvailabilityCommand(
            $this->account->requireAccount(),
            $id,
            VolunteeringInput::expectedVersion($body),
            VolunteeringInput::weekly($body),
            VolunteeringInput::exceptions($body),
        ));

        return new Response(status: 204);
    }

    #[Route('/api/volunteering/volunteers', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function volunteers(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(VolunteeringJson::volunteers($this->list->handle(new ListVolunteersQuery($search, $limit, $offset))));
    }

    #[Route('/api/volunteering/volunteers/{id}', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function volunteer(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(VolunteeringJson::profile($this->get->handle(new GetVolunteerQuery($id))));
    }

    #[Route('/api/volunteering/credential-types', methods: ['GET'])]
    #[IsGranted('volunteering.read')]
    public function credentialTypes(): JsonResponse
    {
        return new JsonResponse(array_map(VolunteeringJson::credentialType(...), $this->types->handle(new ListCredentialTypesQuery())));
    }
}
