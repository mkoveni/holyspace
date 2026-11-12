<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\ConfigureVisitLink\ConfigureVisitLinkCommand;
use App\Events\Application\Command\ConvertVisitor\ConvertVisitorCommand;
use App\Events\Application\Command\HandOverVisitorChild\HandOverVisitorChildCommand;
use App\Events\Application\Command\ReviewVisitor\ReviewVisitorCommand;
use App\Events\Application\Contract\VisitorPage;
use App\Events\Application\Query\GetVisitLink\GetVisitLinkHandler;
use App\Events\Application\Query\GetVisitLink\GetVisitLinkQuery;
use App\Events\Application\Query\ListVisitors\ListVisitorsHandler;
use App\Events\Application\Query\ListVisitors\ListVisitorsQuery;
use App\Events\Presentation\Request\ConfigureVisitLinkRequest;
use App\Events\Presentation\Request\ConvertVisitorRequest;
use App\Events\Presentation\Request\HandOverVisitorChildRequest;
use App\Events\Presentation\Request\ReviewVisitorRequest;
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

/** Staff side of visitor self check-in: the QR link, the visitors who used it, and their review. */
#[Route('/api/events')]
final readonly class EventVisitorsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private GetVisitLinkHandler $link,
        private ListVisitorsHandler $visitors,
    ) {
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/occurrences/{id}/visit-link', methods: ['GET'])]
    public function link(#[PathUuid] string $id): JsonResponse
    {
        $view = $this->link->handle(new GetVisitLinkQuery($id));

        return new JsonResponse(['occurrenceId' => $view->occurrenceId, 'enabled' => $view->enabled, 'token' => $view->token, 'messages' => $view->messages]);
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/occurrences/{id}/visit-link', methods: ['POST'])]
    public function configure(#[PathUuid] string $id, #[MapRequestPayload] ConfigureVisitLinkRequest $request): JsonResponse
    {
        $this->commands->dispatch(new ConfigureVisitLinkCommand($this->account->requireAccount(), $id, $request->action));

        return $this->link($id);
    }

    #[IsGranted('events.visitors.read')]
    #[Route('/occurrences/{id}/visitors', methods: ['GET'])]
    public function list(
        #[PathUuid] string $id,
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(self::page($this->visitors->handle(new ListVisitorsQuery($id, $status, $limit, $offset))));
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/registrations/{id}/visitor-review', methods: ['POST'])]
    public function review(#[PathUuid] string $id, #[MapRequestPayload] ReviewVisitorRequest $request): Response
    {
        $this->commands->dispatch(new ReviewVisitorCommand($this->account->requireAccount(), $id, $request->decision, $request->expectedAttendanceVersion, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/registrations/{id}/visitor-convert', methods: ['POST'])]
    public function convert(#[PathUuid] string $id, #[MapRequestPayload] ConvertVisitorRequest $request): Response
    {
        $this->commands->dispatch(new ConvertVisitorCommand($this->account->requireAccount(), $id, $request->inviteToApp));

        return new Response(status: 204);
    }

    #[IsGranted('events.attendance.manage')]
    #[Route('/visitor-children/{id}/handover', methods: ['POST'])]
    public function handOverChild(#[PathUuid] string $id, #[MapRequestPayload] HandOverVisitorChildRequest $request): Response
    {
        $this->commands->dispatch(new HandOverVisitorChildCommand($this->account->requireAccount(), $id, $request->decision));

        return new Response(status: 204);
    }

    /** @return array<string, mixed> */
    private static function page(VisitorPage $page): array
    {
        return [
            'total' => $page->total,
            'pending' => $page->pending,
            'visitors' => array_map(static fn ($v): array => [
                'registrationId' => $v->registrationId,
                'fullName' => $v->fullName,
                'mobile' => $v->mobile,
                'email' => $v->email,
                'howHeard' => $v->howHeard,
                'partyAdults' => $v->partyAdults,
                'firstTime' => $v->firstTime,
                'reviewStatus' => $v->reviewStatus,
                'checkedInAt' => Json::instant($v->checkedInAt),
                'attendanceStatus' => $v->attendanceStatus,
                'attendanceVersion' => $v->attendanceVersion,
                'children' => array_map(static fn ($c): array => [
                    'id' => $c->id,
                    'firstName' => $c->firstName,
                    'birthDate' => $c->birthDate->format('Y-m-d'),
                    'handoverStatus' => $c->handoverStatus,
                ], $v->children),
            ], $page->visitors),
        ];
    }
}
