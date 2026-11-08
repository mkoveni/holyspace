<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\ApprovePaymentReceipt\ApprovePaymentReceiptCommand;
use App\Payments\Application\Command\RetryPaymentReceipt\RetryPaymentReceiptCommand;
use App\Payments\Application\Query\GetPaymentReceipt\GetPaymentReceiptHandler;
use App\Payments\Application\Query\GetPaymentReceipt\GetPaymentReceiptQuery;
use App\Payments\Presentation\Request\ApproveReceiptRequest;
use App\Payments\Presentation\Request\ReasonRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Verified donor confirmations: payments.manage and payments.receipts.send. */
#[IsGranted('payments.manage')]
#[IsGranted('payments.receipts.send')]
#[Route('/api/payments/{orderId}/receipts')]
final readonly class PaymentReceiptsController
{
    public function __construct(private CommandBus $commands, private AuthenticatedAccount $account, private GetPaymentReceiptHandler $get)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(#[PathUuid] string $orderId, #[MapQueryParameter] string $chargeId, #[MapQueryParameter] ?string $contactId = null): JsonResponse
    {
        $response = new JsonResponse(PaymentJson::receipt($this->get->handle(new GetPaymentReceiptQuery(
            $orderId,
            Json::uuid($chargeId),
            null === $contactId ? null : Json::uuid($contactId)
        ))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('', methods: ['POST'])]
    public function approve(#[PathUuid] string $orderId, #[MapRequestPayload] ApproveReceiptRequest $b): JsonResponse
    {
        $id = $this->commands->dispatch(new ApprovePaymentReceiptCommand(
            $this->account->requireAccount(),
            strtolower((string) $b->requestId),
            $orderId,
            strtolower((string) $b->chargeId),
            strtolower((string) $b->contactId),
            (string) $b->reviewHash,
            (string) $b->evidence
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/{receiptId}/retries', methods: ['POST'])]
    public function retry(#[PathUuid] string $orderId, #[PathUuid] string $receiptId, #[MapRequestPayload] ReasonRequest $b): Response
    {
        $this->commands->dispatch(new RetryPaymentReceiptCommand($this->account->requireAccount(), $orderId, $receiptId, $b->expectedVersion, (string) $b->reason));

        return new Response(null, 200);
    }
}
