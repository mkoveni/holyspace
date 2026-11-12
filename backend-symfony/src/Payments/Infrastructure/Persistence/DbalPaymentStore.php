<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Application\Contract\ChargeView;
use App\Payments\Application\Contract\PaymentDetail;
use App\Payments\Application\Contract\PaymentHistory;
use App\Payments\Application\Contract\PaymentPage;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Application\Contract\PaymentSummary;
use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Payments\Domain\SettlementReview;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Orders, charges, refunds, notifications, settlement reviews and order history (Java `JooqPaymentStore`). Evidence
 * immutability is enforced by the V21/V22 triggers; every order update advances its version by exactly one.
 */
final readonly class DbalPaymentStore implements PaymentRepository, PaymentReader
{
    private PaymentWrites $writes;

    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
        $this->writes = new PaymentWrites($connection);
    }

    public function find(string $id): ?PaymentOrder
    {
        $row = $this->connection->fetchAssociative('SELECT '.PaymentRows::ORDER.' FROM payments_orders WHERE id = ?', [$id]);

        return false === $row ? null : PaymentRows::order($row);
    }

    public function lock(string $id): ?PaymentOrder
    {
        $row = $this->connection->fetchAssociative('SELECT '.PaymentRows::ORDER.' FROM payments_orders WHERE id = ? FOR UPDATE', [$id]);

        return false === $row ? null : PaymentRows::order($row);
    }

    public function findByGuestAccess(string $accessHash): ?PaymentOrder
    {
        $row = $this->connection->fetchAssociative('SELECT '.PaymentRows::ORDER.' FROM payments_orders WHERE guest_access_hash = ?', [$accessHash]);

        return false === $row ? null : PaymentRows::order($row);
    }

    public function insert(PaymentOrder $o): bool
    {
        return 1 === $this->writes->execute(
            'INSERT INTO payments_orders (id, self_service, configuration_id, donor_id, fund_id, amount_minor, provider, mode, recurring, status, subscription_status, request_hash, created_by, created_at,
                guest_name, guest_email_enc, guest_email_hash, guest_access_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING',
            [$o->id, $o->selfService, $o->configurationId, $o->donorId, $o->fundId, $o->amountMinor, $o->provider, $o->mode, $o->recurring,
                $o->status, $o->subscriptionStatus, $o->requestHash, $o->createdBy, Timestamps::toDatabase($o->createdAt),
                $o->guestName, $o->guestEmailEncrypted, $o->guestEmailHash, $o->guestAccessHash],
            [1 => ParameterType::BOOLEAN, 5 => ParameterType::INTEGER, 8 => ParameterType::BOOLEAN],
        );
    }

    public function ready(string $id, string $checkoutId, string $url, string $fields): void
    {
        $this->writes->execute(
            "UPDATE payments_orders SET checkout_id = ?, checkout_url = ?, checkout_fields = ?, status = 'READY', version = version + 1 WHERE id = ? AND status IN ('PENDING', 'READY')",
            [$checkoutId, $url, $fields, $id],
        );
    }

    public function status(string $id, string $status): void
    {
        $this->writes->execute('UPDATE payments_orders SET status = ?, version = version + 1 WHERE id = ?', [$status, $id]);
    }

    public function review(string $id, ?string $reason): void
    {
        $this->writes->execute('UPDATE payments_orders SET review_reason = ?, version = version + 1 WHERE id = ?', [$reason, $id]);
    }

    public function subscription(string $id, string $status, ?string $encryptedToken, ?string $tokenHash): void
    {
        $this->writes->execute(
            'UPDATE payments_orders SET subscription_status = ?, encrypted_token = ?, token_hash = ?, version = version + 1 WHERE id = ?',
            [$status, $encryptedToken, $tokenHash, $id],
        );
    }

    public function lookup(string $provider, string $mode, ?string $checkoutId, ?string $tokenHash): ?string
    {
        if (null === $checkoutId && null === $tokenHash) {
            return null;
        }
        $id = null === $checkoutId
            ? $this->connection->fetchOne('SELECT id FROM payments_orders WHERE provider = ? AND mode = ? AND token_hash = ?', [$provider, $mode, $tokenHash])
            : $this->connection->fetchOne('SELECT id FROM payments_orders WHERE provider = ? AND mode = ? AND checkout_id = ?', [$provider, $mode, $checkoutId]);

        return false === $id ? null : (string) $id;
    }

    public function notification(string $provider, string $mode, string $eventId): ?string
    {
        $fingerprint = $this->connection->fetchOne(
            'SELECT fingerprint FROM payments_notifications WHERE provider = ? AND mode = ? AND event_id = ?',
            [$provider, $mode, $eventId],
        );

        return false === $fingerprint ? null : (string) $fingerprint;
    }

    public function remember(string $provider, string $mode, string $eventId, string $orderId, string $fingerprint, DateTimeImmutable $now): bool
    {
        return 1 === $this->writes->execute(
            'INSERT INTO payments_notifications (provider, mode, event_id, order_id, fingerprint, received_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING',
            [$provider, $mode, $eventId, $orderId, $fingerprint, Timestamps::toDatabase($now)],
        );
    }

    public function charge(string $provider, string $mode, string $reference): ?Charge
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.PaymentRows::CHARGE.' FROM payments_charges WHERE provider = ? AND mode = ? AND reference = ?',
            [$provider, $mode, $reference],
        );

        return false === $row ? null : PaymentRows::charge($row);
    }

    public function insertCharge(Charge $c): bool
    {
        return 1 === $this->writes->execute(
            'INSERT INTO payments_charges (id, order_id, provider, mode, reference, amount_minor, received_on, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING',
            [$c->id, $c->orderId, $c->provider, $c->mode, $c->reference, $c->amountMinor, $c->receivedOn->iso(), Timestamps::toDatabase($c->createdAt)],
            [5 => ParameterType::INTEGER],
        );
    }

    public function charges(string $orderId): array
    {
        return $this->chargeRows('order_id = ? ORDER BY created_at, id LIMIT 1001', [$orderId]);
    }

    public function pendingCharges(string $orderId): array
    {
        return $this->chargeRows('order_id = ? AND gift_id IS NULL ORDER BY created_at, id LIMIT 100', [$orderId]);
    }

    public function matchingCharges(string $orderId, ?string $chargeId): array
    {
        return null === $chargeId
            ? $this->chargeRows('order_id = ? ORDER BY id LIMIT 2', [$orderId])
            : $this->chargeRows('order_id = ? AND id = ? ORDER BY id LIMIT 2', [$orderId, $chargeId]);
    }

    public function posted(string $chargeId, string $giftId): void
    {
        $this->writes->execute('UPDATE payments_charges SET gift_id = ? WHERE id = ? AND gift_id IS NULL', [$giftId, $chargeId]);
    }

    public function refund(string $provider, string $mode, string $reference): ?Refund
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.PaymentRows::REFUND.' FROM payments_refunds WHERE provider = ? AND mode = ? AND reference = ?',
            [$provider, $mode, $reference],
        );

        return false === $row ? null : PaymentRows::refund($row);
    }

    public function insertRefund(Refund $r): bool
    {
        return 1 === $this->writes->execute(
            'INSERT INTO payments_refunds (id, order_id, charge_id, provider, mode, reference, amount_minor, refunded_on, reason, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING',
            [$r->id, $r->orderId, $r->chargeId, $r->provider, $r->mode, $r->reference, $r->amountMinor, $r->refundedOn->iso(), $r->reason,
                $r->createdBy, Timestamps::toDatabase($r->createdAt)],
            [6 => ParameterType::INTEGER],
        );
    }

    public function refunds(string $orderId): array
    {
        return $this->refundRows('order_id = ? ORDER BY created_at, id LIMIT 1001', [$orderId]);
    }

    public function pendingRefunds(string $orderId): array
    {
        return $this->refundRows('order_id = ? AND NOT applied ORDER BY created_at, id LIMIT 100', [$orderId]);
    }

    public function applied(string $refundId, string $chargeId): void
    {
        $this->writes->execute('UPDATE payments_refunds SET applied = TRUE, charge_id = ? WHERE id = ? AND NOT applied', [$chargeId, $refundId]);
    }

    public function matchRefund(string $refundId, string $chargeId): void
    {
        $this->writes->execute('UPDATE payments_refunds SET charge_id = ? WHERE id = ? AND charge_id IS NULL AND NOT applied', [$chargeId, $refundId]);
    }

    public function refunded(string $chargeId): int
    {
        return (int) $this->connection->fetchOne('SELECT COALESCE(sum(amount_minor), 0) FROM payments_refunds WHERE charge_id = ? AND applied', [$chargeId]);
    }

    public function reviewRequest(string $requestId): ?SettlementReview
    {
        $row = $this->connection->fetchAssociative('SELECT '.PaymentRows::REVIEW.' FROM payments_reviews WHERE id = ?', [$requestId]);

        return false === $row ? null : PaymentRows::review($row);
    }

    public function latestReview(string $chargeId): ?SettlementReview
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.PaymentRows::REVIEW.' FROM payments_reviews WHERE charge_id = ? ORDER BY version DESC LIMIT 1',
            [$chargeId],
        );

        return false === $row ? null : PaymentRows::review($row);
    }

    public function insertReview(SettlementReview $r): void
    {
        $this->writes->execute(
            'INSERT INTO payments_reviews (id, order_id, charge_id, version, settlement_reference, fee_minor, bank_minor, refunded_minor, note, created_by, created_at, request_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$r->id, $r->orderId, $r->chargeId, $r->version, $r->settlementReference, $r->feeMinor, $r->bankMinor, $r->refundedMinor, $r->note,
                $r->createdBy, Timestamps::toDatabase($r->createdAt), $r->requestHash],
            [3 => ParameterType::INTEGER, 5 => ParameterType::INTEGER, 6 => ParameterType::INTEGER, 7 => ParameterType::INTEGER],
        );
    }

    public function audit(string $orderId, string $action, string $note, ?string $actorId, DateTimeImmutable $now): void
    {
        $this->writes->execute(
            'INSERT INTO payments_history (id, order_id, action, note, actor_id, occurred_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Uuid::v4()->toRfc4122(), $orderId, $action, $note, $actorId, Timestamps::toDatabase($now)],
        );
    }

    public function list(int $limit, int $offset, bool $reviewOnly): PaymentPage
    {
        $where = $reviewOnly ? 'review_reason IS NOT NULL' : 'TRUE';
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.PaymentRows::ORDER.' FROM payments_orders WHERE '.$where.' ORDER BY created_at DESC, id LIMIT ? OFFSET ?',
            [$limit, $offset],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );

        return new PaymentPage(
            array_map(fn (array $row): PaymentSummary => $this->summary(PaymentRows::order($row)), $rows),
            (int) $this->connection->fetchOne('SELECT count(*) FROM payments_orders WHERE '.$where),
        );
    }

    public function detail(string $id): ?PaymentDetail
    {
        $order = $this->find($id);
        if (null === $order) {
            return null;
        }
        $charges = $this->charges($id);
        $refunds = $this->refunds($id);
        if (\count($charges) > 1000 || \count($refunds) > 1000) {
            throw PaymentException::conflict('This payment has too much history for this view. Contact support for an archival report.');
        }
        $history = $this->connection->fetchAllAssociative(
            'SELECT action, note, actor_id, occurred_at FROM payments_history WHERE order_id = ? ORDER BY occurred_at DESC, id LIMIT 100',
            [$id],
        );

        return new PaymentDetail(
            $this->summary($order),
            array_map(fn (Charge $c): ChargeView => new ChargeView($c, $this->refunded($c->id), $this->latestReview($c->id)), $charges),
            $refunds,
            array_map(static fn (array $h): PaymentHistory => new PaymentHistory(
                (string) $h['action'],
                (string) $h['note'],
                PaymentRows::text($h['actor_id']),
                Timestamps::fromDatabase((string) $h['occurred_at'])
            ), $history),
        );
    }

    private function summary(PaymentOrder $o): PaymentSummary
    {
        $giving = match (true) {
            !$o->isLive() => 'SIMULATION',
            'PAID' !== $o->status => 'AWAITING_PAYMENT',
            false !== $this->connection->fetchOne('SELECT 1 FROM payments_charges WHERE order_id = ? AND gift_id IS NULL LIMIT 1', [$o->id]) => 'POSTING_PENDING',
            default => 'POSTED',
        };

        return new PaymentSummary(
            $o->id,
            $o->donorId,
            null === $o->donorId ? 'Public giver' : ($this->people->get($o->donorId)->name ?? 'Person unavailable'),
            $o->fundId,
            $o->amountMinor,
            $o->provider,
            $o->mode,
            $o->recurring,
            $o->status,
            $o->subscriptionStatus,
            $o->reviewReason,
            $o->version,
            $o->createdAt,
            $giving
        );
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<Charge>
     */
    private function chargeRows(string $condition, array $params): array
    {
        return array_map(PaymentRows::charge(...), $this->connection->fetchAllAssociative('SELECT '.PaymentRows::CHARGE.' FROM payments_charges WHERE '.$condition, $params));
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<Refund>
     */
    private function refundRows(string $condition, array $params): array
    {
        return array_map(PaymentRows::refund(...), $this->connection->fetchAllAssociative('SELECT '.PaymentRows::REFUND.' FROM payments_refunds WHERE '.$condition, $params));
    }
}
