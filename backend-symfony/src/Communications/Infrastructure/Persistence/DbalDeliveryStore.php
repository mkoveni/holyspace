<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\DeliveryEvent;
use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliveryReceipt;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\DeliverySummary;
use App\Communications\Application\DeliveryMapping;
use App\Communications\Domain\EmailDelivery;
use App\Communications\Domain\Repository\DeliveryRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalDeliveryStore implements DeliveryReader, DeliveryRepository
{
    private const string TABLE = 'communication_email_deliveries';
    private const string SUMMARY = 'id, communication_id, status, scheduled_at, attempts, error_code, captured, version';

    public function __construct(private Connection $connection, private IdGenerator $ids)
    {
    }

    public function insert(EmailDelivery $d): void
    {
        $this->connection->insert(self::TABLE, ['id' => $d->id, 'communication_id' => $d->communicationId, 'verification_id' => $d->verificationId, 'contact_id' => $d->contactId, 'recipient' => $d->recipient, 'subject' => $d->subject, 'body' => $d->body, 'content_type' => $d->contentType, 'template_key' => $d->templateKey, 'template_version' => $d->templateVersion, 'review_hash' => $d->reviewHash, 'approved_by' => $d->approvedBy, 'approved_at' => Timestamps::toDatabase($d->approvedAt), 'scheduled_at' => Timestamps::toDatabase($d->scheduledAt), 'status' => $d->status, 'message_id' => $d->messageId]);
    }

    public function lock(string $id): ?EmailDelivery
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_email_deliveries WHERE id = :id FOR UPDATE', ['id' => $id]);

        return false === $row ? null : self::fromRow($row);
    }

    public function save(EmailDelivery $d): void
    {
        $time = static fn (?DateTimeImmutable $value): ?string => null === $value ? null : Timestamps::toDatabase($value);
        $this->connection->update(self::TABLE, [
            'status' => $d->status,
            'approved_by' => $d->approvedBy,
            'approved_at' => Timestamps::toDatabase($d->approvedAt),
            'scheduled_at' => Timestamps::toDatabase($d->scheduledAt),
            'attempts' => $d->attempts,
            'attempt_id' => $d->attemptId,
            'started_at' => $time($d->startedAt),
            'finished_at' => $time($d->finishedAt),
            'error_code' => $d->errorCode,
            'captured' => $d->captured,
            'version' => $d->version,
        ], ['id' => $d->id], ['captured' => ParameterType::BOOLEAN]);
    }

    public function event(string $deliveryId, string $type, DateTimeImmutable $at, ?string $actorId, ?string $eventKey, ?string $eventHash, string $reference): void
    {
        $this->connection->insert('communication_delivery_events', ['id' => $this->ids->uuid(), 'delivery_id' => $deliveryId, 'type' => $type, 'occurred_at' => Timestamps::toDatabase($at), 'actor_id' => $actorId, 'event_key' => $eventKey, 'event_hash' => $eventHash, 'reference' => $reference]);
    }

    public function recoverStale(DateTimeImmutable $before, DateTimeImmutable $now): int
    {
        $ids = $this->connection->fetchFirstColumn(
            "SELECT id FROM communication_email_deliveries WHERE status = 'SENDING' AND started_at < :before LIMIT 100 FOR UPDATE SKIP LOCKED",
            ['before' => Timestamps::toDatabase($before)],
        );
        foreach ($ids as $id) {
            $d = $this->lock((string) $id);
            if (null === $d) {
                continue;
            }
            $this->save($d->transition('UNKNOWN', $d->scheduledAt, $now, 'PROCESS_INTERRUPTED', false));
            $this->event((string) $id, 'UNKNOWN', $now, null, null, null, 'Sending was interrupted; reconcile with the provider before any new attempt.');
        }

        return \count($ids);
    }

    public function summaries(array $communicationIds): array
    {
        if ([] === $communicationIds) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative('SELECT '.self::SUMMARY.' FROM communication_email_deliveries WHERE communication_id IN (:ids)', ['ids' => $communicationIds], ['ids' => ArrayParameterType::STRING]);
        $found = [];
        foreach ($rows as $row) {
            $found[(string) $row['communication_id']] = self::summary($row);
        }

        return $found;
    }

    public function delivery(string $id): ?DeliverySnapshot
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_email_deliveries WHERE id = :id', ['id' => $id]);

        return false === $row ? null : DeliveryMapping::snapshot(self::fromRow($row));
    }

    public function forCommunication(string $communicationId): ?DeliverySnapshot
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_email_deliveries WHERE communication_id = :id', ['id' => $communicationId]);

        return false === $row ? null : DeliveryMapping::snapshot(self::fromRow($row));
    }

    public function events(string $deliveryId, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, type, occurred_at, reference FROM communication_delivery_events WHERE delivery_id = :id ORDER BY occurred_at DESC, id LIMIT :limit OFFSET :offset', ['id' => $deliveryId, 'limit' => $limit, 'offset' => $offset]);

        return array_map(static fn (array $r): DeliveryEvent => new DeliveryEvent((string) $r['id'], (string) $r['type'], Timestamps::fromDatabase((string) $r['occurred_at']), (string) $r['reference']), $rows);
    }

    public function list(?string $status, int $limit, int $offset): DeliveryPage
    {
        return $this->page('TRUE', [], $status, $limit, $offset);
    }

    public function listForSource(string $sourceContext, ?string $status, int $limit, int $offset): DeliveryPage
    {
        return $this->page('id IN (SELECT delivery_id FROM communication_delivery_sources WHERE source_context = :source)', ['source' => $sourceContext], $status, $limit, $offset);
    }

    public function belongsTo(string $id, string $sourceContext): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM communication_delivery_sources WHERE delivery_id = :id AND source_context = :source', ['id' => $id, 'source' => $sourceContext]);
    }

    public function due(DateTimeImmutable $at, int $limit): array
    {
        $ids = $this->connection->fetchFirstColumn("SELECT id FROM communication_email_deliveries WHERE status IN ('QUEUED', 'SCHEDULED') AND scheduled_at <= :at ORDER BY scheduled_at, id LIMIT :limit", ['at' => Timestamps::toDatabase($at), 'limit' => $limit]);

        return array_map('strval', $ids);
    }

    public function receipt(string $eventKey): ?DeliveryReceipt
    {
        $row = $this->connection->fetchAssociative('SELECT event_hash, delivery_id FROM communication_delivery_events WHERE event_key = :key', ['key' => $eventKey]);

        return false === $row ? null : new DeliveryReceipt((string) $row['event_hash'], (string) $row['delivery_id']);
    }

    /** @param array<string, mixed> $params */
    private function page(string $scope, array $params, ?string $status, int $limit, int $offset): DeliveryPage
    {
        $filtered = $scope;
        $filterParams = $params;
        if (null !== $status && '' !== trim($status) && 'ALL' !== $status) {
            $filtered .= ' AND status = :status';
            $filterParams['status'] = $status;
        }
        $failed = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM communication_email_deliveries WHERE $scope AND status IN ('FAILED', 'BLOCKED', 'UNKNOWN')", $params);
        $waiting = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM communication_email_deliveries WHERE $scope AND status IN ('QUEUED', 'SCHEDULED', 'SENDING')", $params);
        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM communication_email_deliveries WHERE $filtered", $filterParams);
        $rows = $this->connection->fetchAllAssociative('SELECT '.self::SUMMARY." FROM communication_email_deliveries WHERE $filtered ORDER BY scheduled_at DESC, id LIMIT :limit OFFSET :offset", $filterParams + ['limit' => $limit, 'offset' => $offset]);

        return new DeliveryPage(array_map(self::summary(...), $rows), $total, $failed, $waiting);
    }

    /** @param array<string, mixed> $r */
    private static function summary(array $r): DeliverySummary
    {
        return new DeliverySummary((string) $r['id'], (string) $r['communication_id'], (string) $r['status'], Timestamps::fromDatabase((string) $r['scheduled_at']), (int) $r['attempts'], (string) $r['error_code'], (bool) $r['captured'], (int) $r['version']);
    }

    /** @param array<string, mixed> $r */
    private static function fromRow(array $r): EmailDelivery
    {
        $time = static fn (mixed $v): ?DateTimeImmutable => null === $v ? null : Timestamps::fromDatabase((string) $v);
        $text = static fn (mixed $v): ?string => null === $v ? null : (string) $v;

        return new EmailDelivery(
            (string) $r['id'],
            (string) $r['communication_id'],
            $text($r['verification_id']),
            $text($r['contact_id']),
            (string) $r['recipient'],
            (string) $r['subject'],
            (string) $r['body'],
            trim((string) $r['review_hash']),
            (string) $r['approved_by'],
            Timestamps::fromDatabase((string) $r['approved_at']),
            Timestamps::fromDatabase((string) $r['scheduled_at']),
            (string) $r['status'],
            (int) $r['attempts'],
            (string) $r['message_id'],
            $text($r['attempt_id']),
            $time($r['started_at']),
            $time($r['finished_at']),
            (string) $r['error_code'],
            (bool) $r['captured'],
            (int) $r['version'],
            (string) $r['content_type'],
            $text($r['template_key']),
            null === $r['template_version'] ? null : (int) $r['template_version'],
        );
    }
}
