<?php

declare(strict_types=1);

namespace App\Communications\Application\Port;

interface MessageGateway extends DeliveryConfiguration
{
    public function send(string $deliveryId, string $channel, string $destination, string $subject, string $body): void;

    public function configuration(): array;
}
