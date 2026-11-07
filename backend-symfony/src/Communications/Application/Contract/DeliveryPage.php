<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

final readonly class DeliveryPage
{
    /** @param list<DeliverySummary> $deliveries */
    public function __construct(public array $deliveries, public int $total, public int $failed, public int $waiting)
    {
    }
}
