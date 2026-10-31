<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentWorkspace;

use App\Giving\Application\ReadModel\OnlineGivingFunds;
use App\Payments\Application\Port\ProviderCatalogue;
use App\Payments\Application\ReadModel\PaymentReadStore;
use App\Payments\Domain\Model\OnlineGivingSettings;
use App\Payments\Domain\Model\PaymentOrder;
use App\People\Application\ReadModel\PersonDirectory;

final readonly class GetPaymentWorkspaceHandler
{
    public function __construct(private PaymentReadStore $records, private ProviderCatalogue $gateway, private OnlineGivingFunds $giving, private PersonDirectory $people)
    {
    }

    public function __invoke(GetPaymentWorkspaceQuery $message): array
    {
        $givingAccess = $message->givingAccess;
        $peopleAccess = $message->peopleAccess;
        $records = $this->records->all('orders');
        $people = $peopleAccess ? $this->people->getMany(array_column($records, 'personId')) : [];
        $charges = $this->groupByOrder($this->records->all('charges'));
        $refunds = $this->groupByOrder($this->records->all('refunds'));
        $orders = array_map(function ($order) use ($peopleAccess, $people, $charges, $refunds) {
            $item = $this->view($order, $charges[$order->id] ?? [], $refunds[$order->id] ?? []);
            if ($peopleAccess) {
                $item['personName'] = $people[$order->personId]['name'] ?? 'Person unavailable';
            }

            return $item;
        }, $records);

        return ['providers' => $this->gateway->providers(), 'settings' => get_object_vars($this->settings()), 'funds' => $givingAccess ? $this->giving->funds() : [], 'orders' => $orders, 'currency' => 'ZAR'];
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

    private function settings(): OnlineGivingSettings
    {
        return $this->records->find('settings', OnlineGivingSettings::ID) ?? new OnlineGivingSettings();
    }

    private function charges(string $id): array
    {
        return array_values(array_filter($this->records->all('charges'), fn ($charge) => $charge->orderId === $id));
    }
}
