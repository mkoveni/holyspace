<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use App\Volunteering\Application\Command\RespondMyVolunteerInvitation\RespondMyVolunteerInvitationCommand;
use App\Volunteering\Application\Command\UpdateMyVolunteerAvailability\UpdateMyVolunteerAvailabilityCommand;
use App\Volunteering\Application\Query\GetMyVolunteering\GetMyVolunteeringHandler;
use App\Volunteering\Application\Query\GetMyVolunteering\GetMyVolunteeringQuery;
use App\Volunteering\Presentation\Request\RespondRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Java `MemberVolunteeringController`: my duties, answers and availability. Responses are `no-store`, as in Java. */
final readonly class MemberVolunteeringController
{
    public function __construct(private CommandBus $commands, private GetMyVolunteeringHandler $overview)
    {
    }

    #[Route('/api/member/volunteering', methods: ['GET'])]
    public function overview(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return self::private(new JsonResponse(VolunteeringJson::overview($this->overview->handle(new GetMyVolunteeringQuery($limit, $offset)))));
    }

    #[Route('/api/member/volunteering/invitations/{id}/respond', methods: ['POST'])]
    #[IsGranted('volunteering.serve')]
    public function respond(#[PathUuid] string $id, #[MapRequestPayload] RespondRequest $request): Response
    {
        $this->commands->dispatch(new RespondMyVolunteerInvitationCommand($id, $request->expectedVersion, $request->decision));

        return self::private(new Response(status: 204));
    }

    #[Route('/api/member/volunteering/availability', methods: ['PUT'])]
    #[IsGranted('volunteering.serve')]
    public function availability(Request $request): Response
    {
        $body = $request->toArray();
        $volunteerId = VolunteeringInput::uuid($body, 'volunteerId');
        if (null === $volunteerId) {
            return self::private(new JsonResponse(['message' => 'Invalid request. Check the required fields and their formats.'], 400));
        }
        $this->commands->dispatch(new UpdateMyVolunteerAvailabilityCommand(
            $volunteerId,
            VolunteeringInput::expectedVersion($body),
            VolunteeringInput::weekly($body),
            VolunteeringInput::exceptions($body),
        ));

        return self::private(new Response(status: 204));
    }

    /**
     * @template T of Response
     *
     * @param T $response
     *
     * @return T
     */
    private static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
