<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\MobileCheckout;
use App\Payments\Domain\Repository\MobileCheckoutRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalMobileCheckoutStore implements MobileCheckoutRepository
{
    private PaymentWrites $writes;

    public function __construct(private Connection $connection)
    {
        $this->writes = new PaymentWrites($connection);
    }

    public function insert(MobileCheckout $c): void
    {
        $this->writes->execute(
            'INSERT INTO payments_mobile_checkouts (token_hash, order_id, account_id, session_id, encrypted_checkout, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$c->tokenHash, $c->orderId, $c->accountId, $c->sessionId, $c->encryptedCheckout, Timestamps::toDatabase($c->expiresAt)],
        );
    }

    public function lock(string $tokenHash): ?MobileCheckout
    {
        $r = $this->connection->fetchAssociative(
            'SELECT token_hash, order_id, account_id, session_id, encrypted_checkout, expires_at, used_at FROM payments_mobile_checkouts WHERE token_hash = ? FOR UPDATE',
            [$tokenHash],
        );

        return false === $r ? null : new MobileCheckout(
            (string) $r['token_hash'],
            (string) $r['order_id'],
            (string) $r['account_id'],
            (string) $r['session_id'],
            (string) $r['encrypted_checkout'],
            Timestamps::fromDatabase((string) $r['expires_at']),
            null === $r['used_at'] ? null : Timestamps::fromDatabase((string) $r['used_at'])
        );
    }

    public function used(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->writes->execute('UPDATE payments_mobile_checkouts SET used_at = ? WHERE token_hash = ? AND used_at IS NULL', [Timestamps::toDatabase($now), $tokenHash]);
    }
}
