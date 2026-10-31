<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartPayment;

use App\Events\Application\Port\TicketReservations;
use App\Payments\Application\Port\PaymentGateway;
use App\Payments\Domain\Model\OnlineGivingSettings;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class StartPaymentHandler
{
    public function __construct(
        private PaymentRepository $records,
        private PaymentGateway $gateway,
        private TicketReservations $tickets,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(StartPaymentCommand $message): array
    {
        $personId = $message->personId;
        $provider = $message->provider;
        $purpose = $message->purpose;
        $targetId = $message->targetId;
        $amountMinor = $message->amountMinor;
        $recurring = $message->recurring;
        $requestKey = $message->requestKey;
        Input::id($personId);
        Input::id($targetId);
        Input::text($requestKey, 100);
        $person = $this->people->get($personId) ?? throw new OutOfBoundsException('Your linked person could not be found. Ask staff.');
        $mode = $this->gateway->mode($provider);
        $order = $this->transactions->run(function () use ($personId, $provider, $mode, $purpose, $targetId, $amountMinor, $recurring, $requestKey) {
            $prior = $this->records->byExternalKey('request:'.hash('sha256', $personId.':'.$requestKey));
            if ($prior) {
                if ($prior->provider !== $provider || $prior->mode !== $mode || $prior->purpose !== $purpose || $prior->targetId !== $targetId || $prior->recurring !== $recurring || 'giving' === $purpose && $prior->amountMinor !== $amountMinor) {
                    throw new DomainException('This request key was used for another payment.');
                }

                return $prior;
            }
            if ($recurring) {
                foreach ($this->records->all('orders') as $existing) {
                    if ($existing->mode === $mode && $existing->personId === $personId && $existing->targetId === $targetId && $existing->recurring && 'cancelled' !== $existing->subscriptionStatus && 'failed' !== $existing->status) {
                        throw new DomainException('A monthly giving setup already exists for this fund. Resume or cancel it, or ask staff to review it before creating another subscription.');
                    }
                }
            }
            $amount = 'ticket' === $purpose ? $this->tickets->price($targetId) : $amountMinor ?? 0;
            if ('giving' === $purpose && !in_array($targetId, $this->settings()->fundIds, true)) {
                throw new DomainException('This fund is not available for online giving.');
            }
            $expiry = $this->clock->now()->modify('ticket' === $purpose ? '+15 minutes' : '+24 hours')->format(DATE_ATOM);
            $order = new PaymentOrder($personId, $provider, $mode, $purpose, $amount, $targetId, $requestKey, $expiry, $recurring);
            if ('ticket' === $purpose) {
                $this->tickets->reserve($targetId, $personId, $order->id, $expiry);
            }
            $this->records->save($order);

            return $this->order($order->id);
        });
        if (null !== $order->checkout && 'ready' === $order->status && new DateTimeImmutable($order->expiresAt) > $this->clock->now()) {
            return $this->view($order) + ['checkout' => $order->checkout];
        }
        if ('created' !== $order->status || new DateTimeImmutable($order->expiresAt) <= $this->clock->now()) {
            throw new DomainException('This payment has completed, failed or expired. Review its status before starting another payment.');
        }
        // Yoco retries reuse the durable order ID as its provider idempotency key.
        $checkout = $this->gateway->checkout($order, $person['name'], is_string($person['email'] ?? null) ? $person['email'] : '');

        return $this->transactions->run(function () use ($order, $checkout) {
            $current = $this->order($order->id);
            if ('created' === $current->status) {
                $this->records->save($current->change(['checkoutId' => $checkout['id'], 'checkout' => $checkout, 'status' => 'ready']));
                $current = $this->order($order->id);
            } elseif (null !== $current->checkoutId && $current->checkoutId !== $checkout['id']) {
                throw new DomainException('Checkout identity conflicts. Review this payment before retrying.');
            }

            return $this->view($current) + ('ready' === $current->status ? ['checkout' => $current->checkout] : []);
        });
    }

    private function settings(): OnlineGivingSettings
    {
        return $this->records->find('settings', OnlineGivingSettings::ID) ?? new OnlineGivingSettings();
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
