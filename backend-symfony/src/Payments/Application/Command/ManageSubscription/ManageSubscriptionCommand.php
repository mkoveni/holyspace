<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ManageSubscription;

final readonly class ManageSubscriptionCommand
{
    public function __construct(
        public string $actorId,
        public string $orderId,
        public int $expectedVersion,
        public bool $cancel,
        public bool $selfService = false,
    ) {
    }
}
