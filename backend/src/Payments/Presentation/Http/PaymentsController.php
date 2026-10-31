<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http;

use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountQuery;
use App\Payments\Application\Command\CancelPayment\CancelPaymentCommand;
use App\Payments\Application\Command\CancelPayment\CancelPaymentHandler;
use App\Payments\Application\Command\ConfigureOnlineFunds\ConfigureOnlineFundsCommand;
use App\Payments\Application\Command\ConfigureOnlineFunds\ConfigureOnlineFundsHandler;
use App\Payments\Application\Command\ReconcilePayment\ReconcilePaymentCommand;
use App\Payments\Application\Command\ReconcilePayment\ReconcilePaymentHandler;
use App\Payments\Application\Command\RecordConfirmedRefund\RecordConfirmedRefundCommand;
use App\Payments\Application\Command\RecordConfirmedRefund\RecordConfirmedRefundHandler;
use App\Payments\Application\Command\StartPayment\StartPaymentCommand;
use App\Payments\Application\Command\StartPayment\StartPaymentHandler;
use App\Payments\Application\Command\SyncSubscription\SyncSubscriptionCommand;
use App\Payments\Application\Command\SyncSubscription\SyncSubscriptionHandler;
use App\Payments\Application\Query\GetMemberPayments\GetMemberPaymentsHandler;
use App\Payments\Application\Query\GetMemberPayments\GetMemberPaymentsQuery;
use App\Payments\Application\Query\GetOwnedPayment\GetOwnedPaymentHandler;
use App\Payments\Application\Query\GetOwnedPayment\GetOwnedPaymentQuery;
use App\Payments\Application\Query\GetPaymentWorkspace\GetPaymentWorkspaceHandler;
use App\Payments\Application\Query\GetPaymentWorkspace\GetPaymentWorkspaceQuery;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Presentation\Http\WorkflowController;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payments', format: 'json')]
final class PaymentsController extends WorkflowController
{
    public function __construct(
        private readonly RequireVerifiedAccountHandler $requireVerifiedAccountHandler,
        private readonly GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
        private readonly GetMemberPaymentsHandler $getMemberPaymentsHandler,
        private readonly StartPaymentHandler $startPaymentHandler,
        private readonly GetOwnedPaymentHandler $getOwnedPaymentHandler,
        private readonly CancelPaymentHandler $cancelPaymentHandler,
        private readonly GetPaymentWorkspaceHandler $getPaymentWorkspaceHandler,
        private readonly ConfigureOnlineFundsHandler $configureOnlineFundsHandler,
        private readonly ReconcilePaymentHandler $reconcilePaymentHandler,
        private readonly SyncSubscriptionHandler $syncSubscriptionHandler,
        private readonly RecordConfirmedRefundHandler $recordConfirmedRefundHandler,
    ) {
    }

    private function person(): string
    {
        $this->denyAccessUnlessGranted('member.portal');
        ($this->requireVerifiedAccountHandler)(new RequireVerifiedAccountQuery());

        return ($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->personId() ?? throw new AccessDeniedException('Your account must be linked to a person.');
    }

    #[Route('/member', methods: ['GET']), IsGranted('member.portal')]
    public function member(): JsonResponse
    {
        return $this->result(fn () => ($this->getMemberPaymentsHandler)(new GetMemberPaymentsQuery($this->person())));
    }

    #[Route('/checkout', methods: ['POST']), IsGranted('member.portal')]
    public function checkout(
        #[MapRequestPayload]
        CheckoutPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->startPaymentHandler)(new StartPaymentCommand($this->person(), $p->provider, $p->purpose, $p->targetId, $p->amountMinor, $p->recurring, $p->requestKey)), 201);
    }

    #[Route('/member/{id}', methods: ['GET']), IsGranted('member.portal')]
    public function order(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->getOwnedPaymentHandler)(new GetOwnedPaymentQuery($this->person(), $id)));
    }

    #[Route('/member/{id}/cancel-subscription', methods: ['POST']), IsGranted('member.portal')]
    public function cancel(
        string $id,
        #[MapRequestPayload]
        VersionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->cancelPaymentHandler)(new CancelPaymentCommand($this->person(), $id, $p->expectedVersion)));
    }

    #[Route('', methods: ['GET']), IsGranted('payments.manage')]
    public function staff(): JsonResponse
    {
        return $this->result(fn () => ($this->getPaymentWorkspaceHandler)(new GetPaymentWorkspaceQuery($this->isGranted('giving.manage'), $this->isGranted('people.read'))));
    }

    #[Route('/funds', methods: ['PUT']), IsGranted('payments.manage'), IsGranted('giving.manage')]
    public function funds(
        #[MapRequestPayload]
        FundsPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->configureOnlineFundsHandler)(new ConfigureOnlineFundsCommand($p->fundIds, $p->expectedVersion)));
    }

    #[Route('/{id}/reconcile', methods: ['POST']), IsGranted('payments.manage')]
    public function reconcile(
        string $id,
        #[MapRequestPayload]
        ReconcilePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->reconcilePaymentHandler)(new ReconcilePaymentCommand($id, $p->expectedVersion, $p->note)));
    }

    #[Route('/{id}/subscription-status', methods: ['POST']), IsGranted('payments.manage')]
    public function subscription(
        string $id,
        #[MapRequestPayload]
        VersionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->syncSubscriptionHandler)(new SyncSubscriptionCommand($id, $p->expectedVersion)));
    }

    #[Route('/charges/{id}/refund', methods: ['POST']), IsGranted('payments.manage')]
    public function refund(
        string $id,
        #[MapRequestPayload]
        RefundPayload $p,
    ): JsonResponse {
        return $this->result(function () use ($id, $p) {
            ($this->recordConfirmedRefundHandler)(new RecordConfirmedRefundCommand($id, $p->amountMinor, $p->reference, $p->expectedVersion, $p->confirmed));

            return ['recorded' => true];
        });
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        try {
            $response = parent::result($operation, $status);
        } catch (AccessDeniedException|AccessDenied $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $response = new JsonResponse(['message' => $e->getMessage()], 503);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary('Authorization', false);

        return $response;
    }
}
