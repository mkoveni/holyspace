<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\DeliverySources;
use Doctrine\DBAL\Connection;

final readonly class DbalDeliverySources implements DeliverySources
{
    public function __construct(private Connection $connection)
    {
    }

    public function context(string $deliveryId): ?string
    {
        $context = $this->connection->fetchOne('SELECT source_context FROM communication_delivery_sources WHERE delivery_id = :id', ['id' => $deliveryId]);

        return false === $context ? null : (string) $context;
    }

    public function associate(string $deliveryId, string $context): void
    {
        $this->connection->insert('communication_delivery_sources', ['delivery_id' => $deliveryId, 'source_context' => $context]);
    }
}
