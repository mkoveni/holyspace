<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetMemberPayments;

use App\Events\Application\ReadModel\TicketBookings;
use App\Giving\Application\ReadModel\OnlineGivingFunds;
use App\Payments\Application\Port\ProviderCatalogue;
use App\Payments\Application\ReadModel\PaymentReadStore;
use App\Payments\Domain\Model\OnlineGivingSettings;
use App\Payments\Domain\Model\PaymentOrder;

final readonly class GetMemberPaymentsHandler
{
    public function __construct(private PaymentReadStore $records, private ProviderCatalogue $gateway, private OnlineGivingFunds $giving, private TicketBookings $tickets)
    {
    }

    public function __invoke(GetMemberPaymentsQuery $message): array
    {
        $personId = $message->personId;
        $settings = $this->settings();
        $charges = $this->groupByOrder($this->records->all('charges'));
        $refunds = $this->groupByOrder($this->records->all('refunds'));

        return ['providers' => $this->gateway->providers(), 'currency' => 'ZAR', 'funds' => array_values(array_filter($this->giving->funds(), fn ($fund) => in_array($fund['id'], $settings->fundIds, true))), 'events' => $this->tickets->offers(), 'orders' => array_map(fn ($order) => $this->view($order, $charges[$order->id] ?? [], $refunds[$order->id] ?? []), array_values(array_filter($this->records->all('orders'), fn ($order) => $order->personId === $personId)))];
    }

    private function settings(): OnlineGivingSettings
    {
        return $this->records->find('settings', OnlineGivingSettings::ID) ?? new OnlineGivingSettings();
    }

    private function groupByOrder(array $records): array
    {
        $groups = [];
        foreach ($records as $record) {
            $groups[$record->orderId][] = $record;
        }

        return $groups;
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
