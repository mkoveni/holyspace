<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

interface DeliveryReader
{
    /**
     * @param list<string> $communicationIds
     *
     * @return array<string, DeliverySummary> keyed by communication id
     */
    public function summaries(array $communicationIds): array;

    public function delivery(string $id): ?DeliverySnapshot;

    public function forCommunication(string $communicationId): ?DeliverySnapshot;

    /** @return list<DeliveryEvent> newest first */
    public function events(string $deliveryId, int $limit, int $offset): array;

    public function list(?string $status, int $limit, int $offset): DeliveryPage;

    public function listForSource(string $sourceContext, ?string $status, int $limit, int $offset): DeliveryPage;

    public function belongsTo(string $id, string $sourceContext): bool;

    /** @return list<string> ids of waiting deliveries whose time has come, oldest first */
    public function due(DateTimeImmutable $at, int $limit): array;

    public function receipt(string $eventKey): ?DeliveryReceipt;
}
