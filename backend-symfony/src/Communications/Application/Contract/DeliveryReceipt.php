<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** A provider event already recorded: the hash of what it said and the delivery it was for. */
final readonly class DeliveryReceipt
{
    public function __construct(public string $hash, public string $deliveryId)
    {
    }
}
