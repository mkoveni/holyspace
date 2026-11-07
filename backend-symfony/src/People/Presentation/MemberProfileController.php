<?php

declare(strict_types=1);

namespace App\People\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Command\RemoveMyContact\RemoveMyContactCommand;
use App\People\Application\Command\SaveMyContact\SaveMyContactCommand;
use App\People\Application\Command\UpdateMyProfile\UpdateMyProfileCommand;
use App\People\Application\Query\GetMyProfile\GetMyProfileHandler;
use App\People\Application\Query\GetMyProfile\GetMyProfileQuery;
use App\People\Presentation\Request\MyContactRequest;
use App\People\Presentation\Request\MyProfileRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A member's own profile. No staff permission is involved: the handlers resolve the caller's verified person
 * and never accept a person id, so a member can only ever touch their own record.
 */
#[Route('/api/member/profile')]
final readonly class MemberProfileController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private GetMyProfileHandler $profile,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        $response = new JsonResponse(PeopleJson::myProfile($this->profile->handle(new GetMyProfileQuery())));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('', methods: ['PUT'])]
    public function update(#[MapRequestPayload] MyProfileRequest $request): Response
    {
        $this->commands->dispatch(new UpdateMyProfileCommand(
            $this->account->requireAccount(),
            $request->titleCode,
            $request->firstName,
            $request->lastName,
            $request->gender,
            $request->expectedVersion,
        ));

        return new Response(status: 204);
    }

    #[Route('/contacts', methods: ['PUT'])]
    public function saveContact(#[MapRequestPayload] MyContactRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new SaveMyContactCommand(
            $this->account->requireAccount(),
            null === $request->contactId ? null : Json::uuid($request->contactId),
            $request->kind ?? throw new BadRequestException('Contact kind is required.'),
            $request->value,
            $request->expectedVersion,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/contacts/{contactId}', methods: ['DELETE'])]
    public function removeContact(
        #[PathUuid] string $contactId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 0], validationFailedStatusCode: 400)] int $expectedVersion,
    ): Response {
        $this->commands->dispatch(new RemoveMyContactCommand($this->account->requireAccount(), $contactId, $expectedVersion));

        return new Response(status: 204);
    }
}
