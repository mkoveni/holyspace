<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CancelPayment;

use App\Payments\Application\Port\PaymentGateway;
use App\Payments\Application\Port\SubscriptionTokens;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelPaymentHandler
{
    public function __construct(
        private PaymentRepository $records,
        private PaymentGateway $gateway,
        private WorkflowTransaction $transactions,
        private SubscriptionTokens $vault,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CancelPaymentCommand $message): array
    {
        $personId = $message->personId;
        $id = $message->id;
        $version = $message->version;
        $order = $this->transactions->run(function () use ($personId, $id, $version) {
            $order = $this->order($id);
            if ($order->personId !== $personId) {
                throw new OutOfBoundsException('Subscription not found.');
            }
            $this->version($order->version, $version);
            if (!$order->recurring || null === $order->encryptedToken) {
                throw new DomainException('This payment has no verified subscription. Check Payfast before starting another subscription.');
            }
            if ('cancelled' !== $order->subscriptionStatus) {
                $this->records->save($order->change(['subscriptionStatus' => 'cancel_requested']));
            }

            return $this->order($id);
        });
        if ('cancelled' === $order->subscriptionStatus) {
            return $this->view($order);
        }
        $this->gateway->cancelSubscription($order, $this->vault->decrypt($order->encryptedToken));

        return $this->transactions->run(function () use ($id) {
            $current = $this->order($id);
            $this->records->save($current->change(['subscriptionStatus' => 'cancelled']));

            return $this->view($this->order($id));
        });
    }

    private function order(string $id): PaymentOrder
    {
        return $this->records->find('orders', $id) ?? throw new OutOfBoundsException('Payment not found.');
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This payment changed. Reload before retrying.');
        }
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
