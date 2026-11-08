<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\ManageSubscription\ManageSubscriptionCommand;
use App\Payments\Application\Command\ManageSubscription\ManageSubscriptionHandler;
use App\Payments\Application\Command\ReconcilePayment\ReconcilePaymentCommand;
use App\Payments\Application\Command\ReconcilePayment\ReconcilePaymentHandler;
use App\Payments\Application\Command\RecordRefund\RecordRefundCommand;
use App\Payments\Application\Command\RecordRefund\RecordRefundHandler;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutCommand;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutHandler;
use App\Payments\Application\Command\StartPayment\StartPaymentCommand;
use App\Payments\Application\Query\GetPayment\GetPaymentHandler;
use App\Payments\Application\Query\GetPayment\GetPaymentQuery;
use App\Payments\Application\Query\ListPaymentProviders\ListPaymentProvidersHandler;
use App\Payments\Application\Query\ListPaymentProviders\ListPaymentProvidersQuery;
use App\Payments\Application\Query\ListPayments\ListPaymentsHandler;
use App\Payments\Application\Query\ListPayments\ListPaymentsQuery;
use App\Payments\Presentation\Request\RefundRequest;
use App\Payments\Presentation\Request\ReviewRequest;
use App\Payments\Presentation\Request\StartPaymentRequest;
use App\Payments\Presentation\Request\SubscriptionRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Staff hosted checkout and verified payment evidence. Everything needs payments.manage; refunds also payments.refund,
 * settlement reviews payments.reconcile. Checkout, refund, review and subscription handlers call providers or post to
 * Giving between transactions, so they are invoked directly (provider boundary), not through the command bus.
 */
#[Route('/api/payments')]
final readonly class PaymentsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ResumeCheckoutHandler $resume,
        private RecordRefundHandler $refunds,
        private ReconcilePaymentHandler $reviews,
        private ManageSubscriptionHandler $subscriptions,
        private ListPaymentsHandler $list,
        private GetPaymentHandler $get,
        private ListPaymentProvidersHandler $providers,
    ) {
    }

    #[IsGranted('payments.manage')]
    #[Route('/providers', methods: ['GET'], priority: 1)]
    public function providers(): JsonResponse
    {
        return new JsonResponse(PaymentJson::providers($this->providers->handle(new ListPaymentProvidersQuery())));
    }

    #[IsGranted('payments.manage')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, validationFailedStatusCode: 400)] bool $reviewOnly = false,
    ): JsonResponse {
        return new JsonResponse(PaymentJson::page($this->list->handle(new ListPaymentsQuery($limit, $offset, $reviewOnly))));
    }

    #[IsGranted('payments.manage')]
    #[Route('/{orderId}', methods: ['GET'])]
    public function detail(#[PathUuid] string $orderId): JsonResponse
    {
        return new JsonResponse(PaymentJson::detail($this->get->handle(new GetPaymentQuery($orderId))));
    }

    #[IsGranted('payments.manage')]
    #[Route('', methods: ['POST'])]
    public function start(#[MapRequestPayload] StartPaymentRequest $b): JsonResponse
    {
        $id = $this->commands->dispatch(new StartPaymentCommand(
            $this->account->requireAccount(),
            strtolower((string) $b->requestId),
            strtolower((string) $b->donorId),
            strtolower((string) $b->fundId),
            (int) $b->amountMinor,
            (string) $b->provider,
            true === $b->recurring
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('payments.manage')]
    #[Route('/{orderId}/checkout', methods: ['POST'])]
    public function checkout(#[PathUuid] string $orderId): JsonResponse
    {
        return new JsonResponse(PaymentJson::checkout($this->resume->handle(new ResumeCheckoutCommand($this->account->requireAccount(), $orderId))));
    }

    #[IsGranted('payments.manage')]
    #[IsGranted('payments.refund')]
    #[Route('/{orderId}/refunds', methods: ['POST'])]
    public function refund(#[PathUuid] string $orderId, #[MapRequestPayload] RefundRequest $b): Response
    {
        $this->refunds->handle(new RecordRefundCommand(
            $this->account->requireAccount(),
            $orderId,
            strtolower((string) $b->chargeId),
            (string) $b->providerReference,
            (int) $b->amountMinor,
            CalendarDate::parse((string) $b->refundedOn),
            (string) $b->reason
        ));

        return new Response(null, 204);
    }

    #[IsGranted('payments.manage')]
    #[IsGranted('payments.reconcile')]
    #[Route('/{orderId}/reviews', methods: ['POST'])]
    public function review(#[PathUuid] string $orderId, #[MapRequestPayload] ReviewRequest $b): JsonResponse
    {
        $id = $this->reviews->handle(new ReconcilePaymentCommand(
            $this->account->requireAccount(),
            strtolower((string) $b->requestId),
            $orderId,
            strtolower((string) $b->chargeId),
            $b->expectedVersion,
            (string) $b->settlementReference,
            (int) $b->feeMinor,
            (int) $b->bankMinor,
            (string) $b->note
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('payments.manage')]
    #[Route('/{orderId}/subscription', methods: ['POST'])]
    public function subscription(#[PathUuid] string $orderId, #[MapRequestPayload] SubscriptionRequest $b): Response
    {
        $this->subscriptions->handle(new ManageSubscriptionCommand($this->account->requireAccount(), $orderId, $b->expectedVersion, $b->cancel));

        return new Response(null, 204);
    }
}
