<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** The delivery queue, published to originating contexts. */
interface EmailDeliveries
{
    /** Stores a new approved delivery for the source context and queues it. */
    public function queue(string $sourceContext, DeliverySnapshot $approved): void;

    public function cancel(string $actor, string $id, int $expected, string $reason): void;

    public function retry(string $actor, string $id, int $expected, string $reason): void;

    /** Records an authenticated provider receipt (DELIVERED or FAILED) exactly once per event key. */
    public function receipt(string $actor, string $id, string $messageId, string $key, string $status, string $reference): void;
}
