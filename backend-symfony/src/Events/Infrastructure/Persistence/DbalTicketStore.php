<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Domain\PublicSale;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\SaleOccurrence;
use App\Events\Domain\TicketFeeSettings;
use App\Events\Domain\TicketOrder;
use App\Events\Domain\TicketOrderLine;
use App\Events\Domain\TicketType;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalTicketStore implements TicketRepository
{
    private const string ORDER = 'id, occurrence_id, status, buyer_name, buyer_email_enc, buyer_email_hash, access_hash, wallet_hash, request_hash, subtotal_minor, fee_minor, total_minor, expires_at, created_at, paid_at, version';
    /** The audit actor of a public buyer's own actions: there is no account (same id as Payments' guest actor). */
    private const string PUBLIC_ACTOR = '00000000-0000-0000-0000-0000000000a1';

    public function __construct(private Connection $connection)
    {
    }

    public function fees(): TicketFeeSettings
    {
        $r = $this->connection->fetchAssociative('SELECT fee_rate_ppm, fee_fixed_minor, provider_vat_ppm, hold_minutes, version FROM event_ticket_settings WHERE id = 1');
        if (false === $r) {
            return new TicketFeeSettings(29500, 0, 150000, 15, 0);
        }

        return new TicketFeeSettings((int) $r['fee_rate_ppm'], (int) $r['fee_fixed_minor'], (int) $r['provider_vat_ppm'], (int) $r['hold_minutes'], (int) $r['version']);
    }

    public function saveFees(TicketFeeSettings $f): void
    {
        $this->connection->executeStatement(
            'UPDATE event_ticket_settings SET fee_rate_ppm = ?, fee_fixed_minor = ?, provider_vat_ppm = ?, hold_minutes = ?, version = ? WHERE id = 1',
            [$f->feeRatePpm, $f->feeFixedMinor, $f->providerVatPpm, $f->holdMinutes, $f->version],
        );
    }

    public function sale(string $occurrenceId): ?PublicSale
    {
        $r = $this->connection->fetchAssociative('SELECT enabled, sales_open_at, sales_close_at, max_per_order, version FROM event_public_sales WHERE occurrence_id = ?', [$occurrenceId]);
        if (false === $r) {
            return null;
        }

        return new PublicSale($occurrenceId, (bool) $r['enabled'], null === $r['sales_open_at'] ? null : Timestamps::fromDatabase((string) $r['sales_open_at']), null === $r['sales_close_at'] ? null : Timestamps::fromDatabase((string) $r['sales_close_at']), (int) $r['max_per_order'], (int) $r['version']);
    }

    public function saveSale(PublicSale $s): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_public_sales (occurrence_id, enabled, sales_open_at, sales_close_at, max_per_order, version) VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT (occurrence_id) DO UPDATE SET enabled = EXCLUDED.enabled, sales_open_at = EXCLUDED.sales_open_at,
             sales_close_at = EXCLUDED.sales_close_at, max_per_order = EXCLUDED.max_per_order, version = EXCLUDED.version',
            [$s->occurrenceId, $s->enabled, null === $s->opensAt ? null : Timestamps::toDatabase($s->opensAt), null === $s->closesAt ? null : Timestamps::toDatabase($s->closesAt), $s->maxPerOrder, $s->version],
            [1 => ParameterType::BOOLEAN],
        );
    }

    public function types(string $occurrenceId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, occurrence_id, name, description, price_minor, quantity_cap, sort_order, active, version FROM event_ticket_types WHERE occurrence_id = ? ORDER BY sort_order, id',
            [$occurrenceId],
        );

        return array_map(static fn (array $r): TicketType => new TicketType(
            (string) $r['id'],
            (string) $r['occurrence_id'],
            (string) $r['name'],
            (string) $r['description'],
            (int) $r['price_minor'],
            null === $r['quantity_cap'] ? null : (int) $r['quantity_cap'],
            (int) $r['sort_order'],
            (bool) $r['active'],
            (int) $r['version']
        ), $rows);
    }

    public function saveType(TicketType $t): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_ticket_types (id, occurrence_id, name, description, price_minor, quantity_cap, sort_order, active, version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description, price_minor = EXCLUDED.price_minor,
             quantity_cap = EXCLUDED.quantity_cap, sort_order = EXCLUDED.sort_order, active = EXCLUDED.active, version = EXCLUDED.version',
            [$t->id, $t->occurrenceId, $t->name, $t->description, $t->priceMinor, $t->quantityCap, $t->sortOrder, $t->active, $t->version],
            [7 => ParameterType::BOOLEAN],
        );
    }

    public function lockOccurrence(string $occurrenceId): ?SaleOccurrence
    {
        $r = $this->connection->fetchAssociative(
            'SELECT o.status, o.ends_at, d.active, p.capacity FROM event_occurrences o
             JOIN event_definitions d ON d.id = o.event_id
             LEFT JOIN event_registration_policies p ON p.occurrence_id = o.id
             WHERE o.id = ? FOR UPDATE OF o',
            [$occurrenceId],
        );
        if (false === $r) {
            return null;
        }

        return new SaleOccurrence($occurrenceId, (string) $r['status'], Timestamps::fromDatabase((string) $r['ends_at']), null === $r['capacity'] ? null : (int) $r['capacity'], (bool) $r['active']);
    }

    public function seatsTaken(string $occurrenceId, DateTimeImmutable $now): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT (SELECT count(*) FROM event_registrations WHERE occurrence_id = :o AND status = 'CONFIRMED')
                  + (SELECT count(*) FROM event_tickets t JOIN event_ticket_orders x ON x.id = t.order_id WHERE x.occurrence_id = :o AND t.status <> 'VOID')
                  + (SELECT COALESCE(sum(l.quantity), 0) FROM event_ticket_order_lines l JOIN event_ticket_orders x ON x.id = l.order_id
                     WHERE x.occurrence_id = :o AND x.status = 'RESERVED' AND x.expires_at > :now)",
            ['o' => $occurrenceId, 'now' => Timestamps::toDatabase($now)],
        );
    }

    public function typeTaken(string $typeId, DateTimeImmutable $now): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT (SELECT count(*) FROM event_tickets t JOIN event_ticket_order_lines l ON l.id = t.line_id WHERE l.ticket_type_id = :t AND t.status <> 'VOID')
                  + (SELECT COALESCE(sum(l.quantity), 0) FROM event_ticket_order_lines l JOIN event_ticket_orders x ON x.id = l.order_id
                     WHERE l.ticket_type_id = :t AND x.status = 'RESERVED' AND x.expires_at > :now)",
            ['t' => $typeId, 'now' => Timestamps::toDatabase($now)],
        );
    }

    public function find(string $orderId): ?TicketOrder
    {
        return $this->load('id = ?', $orderId, false);
    }

    public function lock(string $orderId): ?TicketOrder
    {
        return $this->load('id = ?', $orderId, true);
    }

    public function byAccess(string $accessHash): ?TicketOrder
    {
        return $this->load('access_hash = ?', $accessHash, false);
    }

    public function byWallet(string $walletHash): ?TicketOrder
    {
        return $this->load('wallet_hash = ?', $walletHash, false);
    }

    public function insert(TicketOrder $o): bool
    {
        $inserted = 1 === $this->connection->executeStatement(
            'INSERT INTO event_ticket_orders ('.self::ORDER.') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING',
            [$o->id, $o->occurrenceId, $o->status, $o->buyerName, $o->buyerEmailEncrypted, $o->buyerEmailHash, $o->accessHash, $o->walletHash, $o->requestHash,
                $o->subtotalMinor, $o->feeMinor, $o->totalMinor, Timestamps::toDatabase($o->expiresAt), Timestamps::toDatabase($o->createdAt),
                null === $o->paidAt ? null : Timestamps::toDatabase($o->paidAt), $o->version],
        );
        if (!$inserted) {
            return false;
        }
        foreach ($o->lines as $line) {
            $this->connection->executeStatement(
                'INSERT INTO event_ticket_order_lines (id, order_id, ticket_type_id, type_name_snapshot, quantity, unit_price_minor) VALUES (?, ?, ?, ?, ?, ?)',
                [$line->id, $o->id, $line->ticketTypeId, $line->typeName, $line->quantity, $line->unitPriceMinor],
            );
        }

        return true;
    }

    public function setStatus(string $orderId, string $status, ?DateTimeImmutable $paidAt = null): void
    {
        $this->connection->executeStatement(
            'UPDATE event_ticket_orders SET status = ?, paid_at = COALESCE(?, paid_at), version = version + 1 WHERE id = ?',
            [$status, null === $paidAt ? null : Timestamps::toDatabase($paidAt), $orderId],
        );
    }

    public function issue(string $orderId, array $tickets): void
    {
        $holder = (string) $this->connection->fetchOne('SELECT buyer_name FROM event_ticket_orders WHERE id = ?', [$orderId]);
        foreach ($tickets as $t) {
            $this->connection->executeStatement(
                "INSERT INTO event_tickets (id, order_id, line_id, code_hash, holder_name, status, version) VALUES (?, ?, ?, ?, ?, 'ISSUED', 0) ON CONFLICT DO NOTHING",
                [$t['id'], $orderId, $t['lineId'], $t['codeHash'], '' === $t['holder'] ? $holder : $t['holder']],
            );
        }
    }

    public function voidAll(string $orderId): void
    {
        $this->connection->executeStatement("UPDATE event_tickets SET status = 'VOID', version = version + 1 WHERE order_id = ? AND status <> 'VOID'", [$orderId]);
    }

    public function tickets(string $orderId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.id, t.line_id, l.type_name_snapshot, t.holder_name, t.status FROM event_tickets t JOIN event_ticket_order_lines l ON l.id = t.line_id
             WHERE t.order_id = ? ORDER BY l.id, t.id',
            [$orderId],
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'], 'lineId' => (string) $r['line_id'], 'typeName' => (string) $r['type_name_snapshot'], 'holder' => (string) $r['holder_name'], 'status' => (string) $r['status'],
        ], $rows);
    }

    public function lapsed(DateTimeImmutable $now, int $limit): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            "SELECT id FROM event_ticket_orders WHERE status = 'RESERVED' AND expires_at <= ? ORDER BY expires_at, id LIMIT ".(int) $limit,
            [Timestamps::toDatabase($now)],
        ));
    }

    public function email(string $orderId): ?array
    {
        $r = $this->connection->fetchAssociative('SELECT id, delivery_id, review_hash, created_at FROM event_ticket_emails WHERE order_id = ? ORDER BY created_at DESC, id LIMIT 1', [$orderId]);

        return false === $r ? null : ['id' => (string) $r['id'], 'deliveryId' => (string) $r['delivery_id'], 'reviewHash' => trim((string) $r['review_hash']), 'createdAt' => Timestamps::fromDatabase((string) $r['created_at'])];
    }

    public function emailById(string $id): ?array
    {
        $r = $this->connection->fetchAssociative('SELECT id, order_id, delivery_id, review_hash FROM event_ticket_emails WHERE id = ?', [$id]);

        return false === $r ? null : ['id' => (string) $r['id'], 'orderId' => (string) $r['order_id'], 'deliveryId' => (string) $r['delivery_id'], 'reviewHash' => trim((string) $r['review_hash'])];
    }

    public function recordEmail(string $id, string $orderId, string $deliveryId, string $reviewHash, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_ticket_emails (id, order_id, delivery_id, review_hash, created_at) VALUES (?, ?, ?, ?, ?)',
            [$id, $orderId, $deliveryId, $reviewHash, Timestamps::toDatabase($at)],
        );
    }

    public function ticketPrices(string $orderId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.id, t.line_id, l.type_name_snapshot, l.unit_price_minor, t.holder_name, t.status, t.credit_note_id FROM event_tickets t
             JOIN event_ticket_order_lines l ON l.id = t.line_id WHERE t.order_id = ? ORDER BY l.id, t.id',
            [$orderId],
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'], 'lineId' => (string) $r['line_id'], 'typeName' => (string) $r['type_name_snapshot'], 'unitPriceMinor' => (int) $r['unit_price_minor'],
            'holder' => (string) $r['holder_name'], 'status' => (string) $r['status'], 'creditNoteId' => null === $r['credit_note_id'] ? null : (string) $r['credit_note_id'],
        ], $rows);
    }

    public function voidTickets(string $orderId, array $ticketIds): int
    {
        if ([] === $ticketIds) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            "UPDATE event_tickets SET status = 'VOID', version = version + 1 WHERE order_id = ? AND status <> 'VOID' AND id IN (?)",
            [$orderId, $ticketIds],
            [1 => \Doctrine\DBAL\ArrayParameterType::STRING],
        );
    }

    public function creditTickets(array $ticketIds, string $creditNoteId): void
    {
        if ([] !== $ticketIds) {
            $this->connection->executeStatement('UPDATE event_tickets SET credit_note_id = ? WHERE id IN (?)', [$creditNoteId, $ticketIds], [1 => \Doctrine\DBAL\ArrayParameterType::STRING]);
        }
    }

    public function lockTicketByCode(string $occurrenceId, string $codeHash): ?array
    {
        $r = $this->connection->fetchAssociative(
            'SELECT t.id, t.order_id, l.type_name_snapshot, t.holder_name, t.status, t.checked_in_at, o.status AS order_status
             FROM event_tickets t JOIN event_ticket_orders o ON o.id = t.order_id JOIN event_ticket_order_lines l ON l.id = t.line_id
             WHERE t.code_hash = ? AND o.occurrence_id = ? FOR UPDATE OF t',
            [$codeHash, $occurrenceId],
        );

        return false === $r ? null : [
            'id' => (string) $r['id'], 'orderId' => (string) $r['order_id'], 'typeName' => (string) $r['type_name_snapshot'], 'holder' => (string) $r['holder_name'],
            'status' => (string) $r['status'], 'checkedInAt' => null === $r['checked_in_at'] ? null : Timestamps::fromDatabase((string) $r['checked_in_at']), 'orderStatus' => (string) $r['order_status'],
        ];
    }

    public function unmatchedRefund(string $orderId): int
    {
        return (int) $this->connection->fetchOne('SELECT unmatched_refund_minor FROM event_ticket_orders WHERE id = ?', [$orderId]);
    }

    public function setUnmatchedRefund(string $orderId, int $amountMinor): void
    {
        $this->connection->executeStatement('UPDATE event_ticket_orders SET unmatched_refund_minor = ?, version = version + 1 WHERE id = ?', [$amountMinor, $orderId]);
    }

    public function checkIn(string $ticketId, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement("UPDATE event_tickets SET status = 'CHECKED_IN', checked_in_at = ?, version = version + 1 WHERE id = ? AND status = 'ISSUED'", [Timestamps::toDatabase($at), $ticketId]);
    }

    public function scanCounts(string $occurrenceId): array
    {
        $r = $this->connection->fetchAssociative(
            "SELECT count(*) FILTER (WHERE t.status = 'ISSUED') AS issued, count(*) FILTER (WHERE t.status = 'CHECKED_IN') AS checked_in, count(*) FILTER (WHERE t.status = 'VOID') AS void
             FROM event_tickets t JOIN event_ticket_orders o ON o.id = t.order_id WHERE o.occurrence_id = ?",
            [$occurrenceId],
        );

        return ['issued' => (int) ($r['issued'] ?? 0), 'checkedIn' => (int) ($r['checked_in'] ?? 0), 'void' => (int) ($r['void'] ?? 0)];
    }

    public function currentOrdersOf(string $emailHash, DateTimeImmutable $now, int $limit): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            "SELECT o.id FROM event_ticket_orders o JOIN event_occurrences e ON e.id = o.occurrence_id
             WHERE o.buyer_email_hash = ? AND o.status IN ('PAID', 'PARTIALLY_REFUNDED') AND e.ends_at > ? ORDER BY o.created_at DESC LIMIT ".max(1, min(10, $limit)),
            [$emailHash, Timestamps::toDatabase($now)],
        ));
    }

    public function audit(string $orderId, string $action, string $reason, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_audit (id, actor_id, action, target_id, reason, occurred_at) VALUES (gen_random_uuid(), ?, ?, ?, ?, ?)',
            [self::PUBLIC_ACTOR, $action, $orderId, mb_substr($reason, 0, 1000), Timestamps::toDatabase($at)],
        );
    }

    private function load(string $where, string $value, bool $lock): ?TicketOrder
    {
        $r = $this->connection->fetchAssociative('SELECT '.self::ORDER.' FROM event_ticket_orders WHERE '.$where.($lock ? ' FOR UPDATE' : ''), [$value]);
        if (false === $r) {
            return null;
        }
        $lines = $this->connection->fetchAllAssociative(
            'SELECT id, ticket_type_id, type_name_snapshot, quantity, unit_price_minor FROM event_ticket_order_lines WHERE order_id = ? ORDER BY id',
            [$r['id']],
        );

        return new TicketOrder(
            (string) $r['id'],
            (string) $r['occurrence_id'],
            (string) $r['status'],
            (string) $r['buyer_name'],
            (string) $r['buyer_email_enc'],
            trim((string) $r['buyer_email_hash']),
            trim((string) $r['access_hash']),
            trim((string) $r['wallet_hash']),
            trim((string) $r['request_hash']),
            (int) $r['subtotal_minor'],
            (int) $r['fee_minor'],
            (int) $r['total_minor'],
            Timestamps::fromDatabase((string) $r['expires_at']),
            Timestamps::fromDatabase((string) $r['created_at']),
            null === $r['paid_at'] ? null : Timestamps::fromDatabase((string) $r['paid_at']),
            (int) $r['version'],
            array_map(static fn (array $l): TicketOrderLine => new TicketOrderLine((string) $l['id'], (string) $l['ticket_type_id'], (string) $l['type_name_snapshot'], (int) $l['quantity'], (int) $l['unit_price_minor']), $lines)
        );
    }
}
