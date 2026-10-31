<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetOwnedPayment;

use App\Payments\Application\ReadModel\PaymentReadStore;
use App\Payments\Domain\Model\PaymentOrder;
use OutOfBoundsException;

final readonly class GetOwnedPaymentHandler
{
    public function __construct(private PaymentReadStore $records)
    {
    }

    public function __invoke(GetOwnedPaymentQuery $message): array
    {
        $personId = $message->personId;
        $id = $message->id;
        $order = $this->order($id);
        if ($order->personId !== $personId) {
            throw new OutOfBoundsException('Payment not found.');
        }

        return $this->view($order);
    }

    private function order(string $id): PaymentOrder
    {
        return $this->records->find('orders', $id) ?? throw new OutOfBoundsException('Payment not found.');
    }

    private function view(PaymentOrder $order, ?array $charges = null, ?array $refunds = null): array
    {
        return ['id' => $order->id, 'personId' => $order->personId, 'provider' => $order->provider, 'mode' => $order->mode, 'purpose' => $order->purpose, 'targetId' => $order->targetId, 'requestKey' => $order->requestKey, 'amountMinor' => $order->amountMinor, 'currency' => 'ZAR', 'status' => $order->status, 'recurring' => $order->recurring, 'subscriptionStatus' => $order->subscriptionStatus, 'expiresAt' => $order->expiresAt, 'createdAt' => $order->createdAt, 'reviewReason' => $order->reviewReason, 'reconciliationNote' => $order->reconciliationNote, 'version' => $order->version, 'charges' => $charges ?? $this->charges($order->id), 'refunds' => $refunds ?? array_values(array_filter($this->records->all('refunds'), fn ($refund) => $refund->orderId === $order->id))];
    }

    private function charges(string $id): array
    {
        return array_values(array_filter($this->records->all('charges'), fn ($charge) => $charge->orderId === $id));
    }
}
