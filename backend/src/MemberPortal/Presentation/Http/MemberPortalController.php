<?php

declare(strict_types=1);

namespace App\MemberPortal\Presentation\Http;

use App\MemberPortal\Application\Command\CancelMemberBooking\CancelMemberBookingCommand;
use App\MemberPortal\Application\Command\CancelMemberBooking\CancelMemberBookingHandler;
use App\MemberPortal\Application\Command\UpdateMemberContact\UpdateMemberContactCommand;
use App\MemberPortal\Application\Command\UpdateMemberContact\UpdateMemberContactHandler;
use App\MemberPortal\Application\Query\GetMemberStatement\GetMemberStatementHandler;
use App\MemberPortal\Application\Query\GetMemberStatement\GetMemberStatementQuery;
use App\MemberPortal\Application\Query\GetMemberWorkspace\GetMemberWorkspaceHandler;
use App\MemberPortal\Application\Query\GetMemberWorkspace\GetMemberWorkspaceQuery;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/member', format: 'json')]
#[IsGranted('member.portal')]
final class MemberPortalController extends WorkflowController
{
    public function __construct(
        private readonly GetMemberWorkspaceHandler $getMemberWorkspaceHandler,
        private readonly UpdateMemberContactHandler $updateMemberContactHandler,
        private readonly CancelMemberBookingHandler $cancelMemberBookingHandler,
        private readonly GetMemberStatementHandler $getMemberStatementHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        return $this->result(fn () => ($this->getMemberWorkspaceHandler)(new GetMemberWorkspaceQuery()));
    }

    #[Route('/contact', methods: ['PUT'])]
    public function contact(
        #[MapRequestPayload]
        ContactPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->updateMemberContactHandler)(new UpdateMemberContactCommand($p->email, $p->phone, $p->expectedRevision)));
    }

    #[Route('/registrations/{id}/cancel', methods: ['POST'])]
    public function cancel(
        string $id,
        #[MapRequestPayload]
        CancellationPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->cancelMemberBookingHandler)(new CancelMemberBookingCommand($id, $p->expectedVersion)));
    }

    #[Route('/giving', methods: ['GET'])]
    public function statement(
        #[MapQueryString(validationFailedStatusCode: 422)]
        StatementQuery $q,
    ): JsonResponse {
        return $this->result(fn () => ($this->getMemberStatementHandler)(new GetMemberStatementQuery($q->from, $q->to)));
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        $response = parent::result($operation, $status);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary('Authorization', false);

        return $response;
    }
}
