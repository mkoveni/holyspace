<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Application\Contract\WebhookOperationReader;
use App\Payments\Domain\Repository\WebhookOperationRepository;
use App\Payments\Domain\WebhookOperation;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

final readonly class DbalWebhookOperationStore implements WebhookOperationRepository, WebhookOperationReader
{
    private const string COLUMNS = 'id, revision_id, mode, kind, target_id, backend_url, request_hash, actor_id, status, webhook_id, encrypted_secret, created_at';

    private PaymentWrites $writes;

    public function __construct(private Connection $connection)
    {
        $this->writes = new PaymentWrites($connection);
    }

    public function find(string $id): ?WebhookOperation
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM payments_webhook_operations WHERE id = ?', [$id]);

        return false === $row ? null : self::map($row);
    }

    public function insert(WebhookOperation $v): void
    {
        $this->writes->execute(
            'INSERT INTO payments_webhook_operations (id, revision_id, mode, kind, target_id, backend_url, request_hash, actor_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$v->id, $v->revisionId, $v->mode, $v->kind, $v->targetId, $v->backendUrl, $v->requestHash, $v->actorId, $v->status, Timestamps::toDatabase($v->createdAt)],
        );
    }

    public function finish(string $id, string $status, ?string $webhookId, ?string $encryptedSecret): void
    {
        $this->writes->execute('UPDATE payments_webhook_operations SET status = ?, webhook_id = ?, encrypted_secret = ? WHERE id = ?', [$status, $webhookId, $encryptedSecret, $id]);
    }

    public function pending(string $mode): array
    {
        return array_map(self::map(...), $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS." FROM payments_webhook_operations WHERE mode = ? AND status IN ('STARTED', 'UNKNOWN', 'READY') ORDER BY created_at DESC LIMIT 20",
            [$mode],
        ));
    }

    public function referenced(string $mode, string $backendUrl): bool
    {
        if (false !== $this->connection->fetchOne("SELECT 1 FROM payments_orders WHERE provider = 'yoco' AND mode = ? AND configuration_id IS NULL LIMIT 1", [$mode])) {
            return true;
        }

        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM payments_orders o JOIN payments_configuration_revisions r ON o.configuration_id = r.id
             WHERE o.provider = 'yoco' AND o.mode = ? AND COALESCE(r.backend_url, r.public_url) = ? LIMIT 1",
            [$mode, $backendUrl],
        );
    }

    /** @param array<string, mixed> $r */
    private static function map(array $r): WebhookOperation
    {
        return new WebhookOperation(
            (string) $r['id'],
            (string) $r['revision_id'],
            (string) $r['mode'],
            (string) $r['kind'],
            PaymentRows::text($r['target_id']),
            (string) $r['backend_url'],
            (string) $r['request_hash'],
            (string) $r['actor_id'],
            (string) $r['status'],
            PaymentRows::text($r['webhook_id']),
            PaymentRows::text($r['encrypted_secret']),
            Timestamps::fromDatabase((string) $r['created_at'])
        );
    }
}
