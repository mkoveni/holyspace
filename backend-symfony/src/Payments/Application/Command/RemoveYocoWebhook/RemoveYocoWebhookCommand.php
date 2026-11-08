<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RemoveYocoWebhook;

final readonly class RemoveYocoWebhookCommand
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $mode,
        public int $expectedVersion,
        public string $reason,
        public string $subscriptionId,
    ) {
    }
}
