<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

interface EmailTransport
{
    public function configuration(): TransportConfiguration;

    public function send(DeliverySnapshot $delivery): TransportResult;
}
