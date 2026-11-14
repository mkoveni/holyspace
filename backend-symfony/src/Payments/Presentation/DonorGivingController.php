<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutCommand;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutHandler;
use App\Payments\Application\Command\StartDonorPayment\StartDonorPaymentCommand;
use App\Payments\Application\Query\GetDonorPayment\GetDonorPaymentHandler;
use App\Payments\Application\Query\GetDonorPayment\GetDonorPaymentQuery;
use App\Payments\Application\Query\GetMyGiving\GetMyGivingHandler;
use App\Payments\Application\Query\GetMyGiving\GetMyGivingQuery;
use App\Payments\Presentation\Request\DonorPaymentRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Donor self-service: any authenticated account, but financial identity comes only from its active verified donor
 * link (never permissions, email or People records). Another donor's order is 404.
 */
#[Route('/api/my-giving')]
final readonly class DonorGivingController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private GetMyGivingHandler $overview,
        private GetDonorPaymentHandler $get,
        private ResumeCheckoutHandler $checkout,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function overview(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $paymentOffset = 0,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $giftOffset = 0,
    ): JsonResponse {
        return self::noStore(PaymentJson::myGiving($this->overview->handle(new GetMyGivingQuery($limit, $paymentOffset, $giftOffset))));
    }

    #[Route('/payments/{orderId}', methods: ['GET'])]
    public function detail(#[PathUuid] string $orderId): JsonResponse
    {
        return self::noStore(PaymentJson::donorDetail($this->get->handle(new GetDonorPaymentQuery($orderId))));
    }

    #[Route('/payments', methods: ['POST'])]
    public function start(#[MapRequestPayload] DonorPaymentRequest $b): JsonResponse
    {
        $id = $this->commands->dispatch(new StartDonorPaymentCommand(
            $this->account->requireAccount(),
            strtolower((string) $b->requestId),
            strtolower((string) $b->fundId),
            (int) $b->amountMinor,
            (string) $b->provider,
            $b->recurring
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/payments/{orderId}/checkout', methods: ['POST'])]
    public function checkout(#[PathUuid] string $orderId): JsonResponse
    {
        return self::noStore(PaymentJson::checkout($this->checkout->handle(new ResumeCheckoutCommand($this->account->requireAccount(), $orderId, true))));
    }

    /** @param array<string, mixed> $body */
    private static function noStore(array $body): JsonResponse
    {
        $response = new JsonResponse($body);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
