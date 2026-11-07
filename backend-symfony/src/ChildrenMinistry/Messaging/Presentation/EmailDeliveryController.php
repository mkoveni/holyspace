<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation;

use App\ChildrenMinistry\Messaging\Application\Command\CancelEmailDelivery\CancelEmailDeliveryCommand;
use App\ChildrenMinistry\Messaging\Application\Command\RecordDeliveryReceipt\RecordDeliveryReceiptCommand;
use App\ChildrenMinistry\Messaging\Application\Command\RetryEmailDelivery\RetryEmailDeliveryCommand;
use App\ChildrenMinistry\Messaging\Application\Command\RevokeGuardianEmail\RevokeGuardianEmailCommand;
use App\ChildrenMinistry\Messaging\Application\Query\GetEmailDelivery\GetEmailDeliveryHandler;
use App\ChildrenMinistry\Messaging\Application\Query\GetEmailDelivery\GetEmailDeliveryQuery;
use App\ChildrenMinistry\Messaging\Application\Query\ListEmailDeliveries\ListEmailDeliveriesHandler;
use App\ChildrenMinistry\Messaging\Application\Query\ListEmailDeliveries\ListEmailDeliveriesQuery;
use App\ChildrenMinistry\Messaging\Presentation\Request\ChangeDeliveryRequest;
use App\ChildrenMinistry\Messaging\Presentation\Request\DeliveryReceiptRequest;
use App\ChildrenMinistry\Messaging\Presentation\Request\RevokeEmailRequest;
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

/** Delivery review and control. Reading and revoking need children.safeguarding; cancel and retry also need children.communications.send. */
#[Route('/api/children')]
final readonly class EmailDeliveryController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListEmailDeliveriesHandler $list,
        private GetEmailDeliveryHandler $get,
    ) {
    }

    #[IsGranted('children.safeguarding')]
    #[Route('/verified-emails/{id}/revoke', methods: ['POST'])]
    public function revoke(#[PathUuid] string $id, #[MapRequestPayload] RevokeEmailRequest $request): Response
    {
        $this->commands->dispatch(new RevokeGuardianEmailCommand($this->account->requireAccount(), $id, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('children.safeguarding')]
    #[Route('/email-deliveries', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] string $status = 'ALL',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(MessagingJson::deliveries($this->list->handle(new ListEmailDeliveriesQuery($status, $limit, $offset))));
    }

    #[IsGranted('children.communications.receipts')]
    #[Route('/email-deliveries/receipts', methods: ['POST'])]
    public function receipt(#[MapRequestPayload] DeliveryReceiptRequest $request): Response
    {
        $this->commands->dispatch(new RecordDeliveryReceiptCommand($this->account->requireAccount(), Json::uuid((string) $request->deliveryId), (string) $request->messageId, (string) $request->eventKey, (string) $request->status, (string) $request->reference));

        return new Response(status: 204);
    }

    #[IsGranted('children.safeguarding')]
    #[Route('/email-deliveries/{id}', methods: ['GET'])]
    public function get(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $response = new JsonResponse(MessagingJson::delivery($this->get->handle(new GetEmailDeliveryQuery($id, $limit, $offset))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[IsGranted('children.communications.send')]
    #[Route('/email-deliveries/{id}/cancel', methods: ['POST'])]
    public function cancel(#[PathUuid] string $id, #[MapRequestPayload] ChangeDeliveryRequest $request): Response
    {
        $this->commands->dispatch(new CancelEmailDeliveryCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('children.communications.send')]
    #[Route('/email-deliveries/{id}/retry', methods: ['POST'])]
    public function retry(#[PathUuid] string $id, #[MapRequestPayload] ChangeDeliveryRequest $request): Response
    {
        $this->commands->dispatch(new RetryEmailDeliveryCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }
}
