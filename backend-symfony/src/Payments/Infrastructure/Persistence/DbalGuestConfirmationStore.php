<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\GuestConfirmation;
use App\Payments\Domain\Repository\GuestConfirmationRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

final readonly class DbalGuestConfirmationStore implements GuestConfirmationRepository
{
    private const string COLUMNS = 'id, order_id, charge_id, gift_id, delivery_id, review_hash, created_at';

    public function __construct(private Connection $connection)
    {
    }

    public function find(string $id): ?GuestConfirmation
    {
        return $this->one('id = ?', $id);
    }

    public function forCharge(string $chargeId): ?GuestConfirmation
    {
        return $this->one('charge_id = ?', $chargeId);
    }

    public function insert(GuestConfirmation $c): void
    {
        $this->connection->executeStatement(
            'INSERT INTO payments_guest_confirmations (id, order_id, charge_id, gift_id, delivery_id, review_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$c->id, $c->orderId, $c->chargeId, $c->giftId, $c->deliveryId, $c->reviewHash, Timestamps::toDatabase($c->createdAt)],
        );
    }

    private function one(string $where, string $value): ?GuestConfirmation
    {
        $r = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM payments_guest_confirmations WHERE '.$where, [$value]);

        return false === $r ? null : new GuestConfirmation(
            (string) $r['id'],
            (string) $r['order_id'],
            (string) $r['charge_id'],
            (string) $r['gift_id'],
            (string) $r['delivery_id'],
            (string) $r['review_hash'],
            Timestamps::fromDatabase((string) $r['created_at']),
        );
    }
}
