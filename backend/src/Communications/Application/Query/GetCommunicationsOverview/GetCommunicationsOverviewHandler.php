<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetCommunicationsOverview;

use App\Communications\Application\Port\DeliveryConfiguration;
use App\Communications\Application\ReadModel\CommunicationsReadStore;

final readonly class GetCommunicationsOverviewHandler
{
    public function __construct(private CommunicationsReadStore $records, private DeliveryConfiguration $gateway)
    {
    }

    public function __invoke(GetCommunicationsOverviewQuery $message): array
    {
        $result = [];
        foreach (['templates', 'preferences', 'campaigns', 'deliveries'] as $kind) {
            $result[$kind] = $this->records->all($kind);
        }
        $result['providers'] = array_map(fn ($channel, $configured) => ['id' => $channel, 'channel' => $channel, 'configured' => $configured], array_keys($this->gateway->configuration()), array_values($this->gateway->configuration()));

        return $result;
    }
}
