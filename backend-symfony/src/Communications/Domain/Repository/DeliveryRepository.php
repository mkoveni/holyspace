<?php

declare(strict_types=1);

namespace App\Communications\Domain\Repository;

use App\Communications\Domain\EmailDelivery;
use DateTimeImmutable;

/** Write side of deliveries. Callers lock a row before changing it. */
interface DeliveryRepository
{
    public function insert(EmailDelivery $delivery): void;

    public function lock(string $id): ?EmailDelivery;

    public function save(EmailDelivery $delivery): void;

    public function event(string $deliveryId, string $type, DateTimeImmutable $at, ?string $actorId, ?string $eventKey, ?string $eventHash, string $reference): void;

    /** Marks deliveries stuck in SENDING since before the cutoff as UNKNOWN; returns how many. */
    public function recoverStale(DateTimeImmutable $before, DateTimeImmutable $now): int;
}
