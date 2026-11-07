<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CancelMyEvent\CancelMyEventCommand;
use App\Events\Application\Command\ConfigureMemberEvent\ConfigureMemberEventCommand;
use App\Events\Application\Command\IssueMyEventPass\IssueMyEventPassCommand;
use App\Events\Application\Command\RegisterMyEvent\RegisterMyEventCommand;
use App\Events\Application\Command\ScanMemberAttendance\ScanMemberAttendanceCommand;
use App\Events\Application\IssuedPass;
use App\Events\Application\Query\GetMemberOffer\GetMemberOfferHandler;
use App\Events\Application\Query\GetMemberOffer\GetMemberOfferQuery;
use App\Events\Application\Query\GetMyEvent\GetMyEventHandler;
use App\Events\Application\Query\GetMyEvent\GetMyEventQuery;
use App\Events\Application\Query\ListMyEvents\ListMyEventsHandler;
use App\Events\Application\Query\ListMyEvents\ListMyEventsQuery;
use App\Events\Presentation\Request\CancelMyEventRequest;
use App\Events\Presentation\Request\PublishMemberEventRequest;
use App\Events\Presentation\Request\RegisterMyEventRequest;
use App\Events\Presentation\Request\ScanMemberRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Members browse and book events as themselves: the person is never taken from the request. Staff publish events
 * to the catalogue and scan member passes at the door. Responses carry personal data and are never cached.
 */
final readonly class MemberEventsController
{
    /** Any single segment except the literal sub-resources, so a malformed id is a 400 from PathUuid and not a route clash. */
    private const string ID = '(?!venues$|calendar$|attendance-report|deliveries$)[^/]+';

    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListMyEventsHandler $list,
        private GetMyEventHandler $detail,
        private GetMemberOfferHandler $offer,
    ) {
    }

    #[Route('/api/member/events', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] bool $mine = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return $this->private(new JsonResponse(EventsJson::memberPage($this->list->handle(new ListMyEventsQuery($mine, $limit, $offset)))));
    }

    #[Route('/api/member/events/{id}', methods: ['GET'])]
    public function detail(#[PathUuid] string $id): JsonResponse
    {
        return $this->private(new JsonResponse(EventsJson::memberItem($this->detail->handle(new GetMyEventQuery($id)))));
    }

    #[Route('/api/member/events/{id}/registration', methods: ['POST'])]
    public function register(#[PathUuid] string $id, #[MapRequestPayload] RegisterMyEventRequest $request): JsonResponse
    {
        $booking = $this->commands->dispatch(new RegisterMyEventCommand($this->account->requireAccount(), strtolower((string) $request->requestId), $id, $request->expectedVersion));

        return $this->private(new JsonResponse(['id' => $booking], 201));
    }

    #[Route('/api/member/events/{id}/cancel', methods: ['POST'])]
    public function cancel(#[PathUuid] string $id, #[MapRequestPayload] CancelMyEventRequest $request): Response
    {
        $this->commands->dispatch(new CancelMyEventCommand($this->account->requireAccount(), $id, $request->expectedVersion));

        return $this->private(new Response(status: 204));
    }

    #[Route('/api/member/events/{id}/attendance-pass', methods: ['POST'])]
    public function pass(#[PathUuid] string $id): JsonResponse
    {
        /** @var IssuedPass $pass */
        $pass = $this->commands->dispatch(new IssueMyEventPassCommand($this->account->requireAccount(), $id));

        return $this->private(new JsonResponse(['credential' => $pass->credential, 'expiresAt' => Json::instant($pass->expiresAt)]));
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/api/events/occurrences/{id}/scan-member', methods: ['POST'])]
    public function scan(#[PathUuid] string $id, #[MapRequestPayload] ScanMemberRequest $request): JsonResponse
    {
        $arrival = $this->commands->dispatch(new ScanMemberAttendanceCommand($this->account->requireAccount(), $id, strtolower((string) $request->requestId), $request->credential));

        return $this->private(new JsonResponse(['id' => $arrival], 201));
    }

    #[IsGranted('events.manage')]
    #[Route('/api/events/{id}/member-offer', methods: ['GET'], requirements: ['id' => self::ID])]
    public function offer(#[PathUuid] string $id): JsonResponse
    {
        return $this->private(new JsonResponse(EventsJson::offer($this->offer->handle(new GetMemberOfferQuery($id)))));
    }

    #[IsGranted('events.manage')]
    #[Route('/api/events/{id}/member-offer', methods: ['PUT'], requirements: ['id' => self::ID])]
    public function publish(#[PathUuid] string $id, #[MapRequestPayload] PublishMemberEventRequest $request): Response
    {
        $this->commands->dispatch(new ConfigureMemberEventCommand($this->account->requireAccount(), $id, $request->enabled, $request->expectedVersion));

        return new Response(status: 204);
    }

    /**
     * @template T of Response
     *
     * @param T $response
     *
     * @return T
     */
    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
