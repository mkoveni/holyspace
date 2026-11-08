<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\RetryPaymentPosting\RetryPaymentPostingCommand;
use App\Payments\Application\Command\RetryPaymentPosting\RetryPaymentPostingHandler;
use App\Payments\Presentation\Request\VersionRequest;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class PaymentPostingController
{
    public function __construct(private AuthenticatedAccount $account, private RetryPaymentPostingHandler $handler)
    {
    }

    #[IsGranted('payments.manage')]
    #[Route('/api/payments/{orderId}/posting-retries', methods: ['POST'])]
    public function retry(#[PathUuid] string $orderId, #[MapRequestPayload] VersionRequest $b): JsonResponse
    {
        return new JsonResponse(PaymentJson::posting($this->handler->handle(new RetryPaymentPostingCommand($this->account->requireAccount(), $orderId, $b->expectedVersion))));
    }
}
