<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use App\Shared\Application\Audit\AuditHistory;
use App\Shared\Application\Exception\FeatureUnavailable;
use App\Shared\Domain\ValueObject\Input;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AuditHistory::class)]
final readonly class DbalAuditHistory implements AuditHistory
{
    public function __construct(private Connection $db)
    {
    }

    public function page(string $context, string $recordId, int $limit = 25, ?int $before = null): array
    {
        Input::id($recordId);
        if ($limit < 1 || $limit > 100 || (null !== $before && $before < 1)) {
            throw new InvalidArgumentException('Invalid audit pagination.');
        }
        if (!$this->db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            throw new FeatureUnavailable('Field-level history requires the PostgreSQL audit migrations. Legacy workflow history is still available.');
        }
        $query = $this->db->createQueryBuilder()->select('a.*, u.username AS actor_name')->from('audit_entries', 'a')->leftJoin('a', 'user_accounts', 'u', "u.id = CASE WHEN pg_input_is_valid(a.actor_id, 'uuid') THEN CAST(a.actor_id AS uuid) END")->where('a.context = :context')->andWhere('a.record_id = :record')->setParameter('context', $context)->setParameter('record', $recordId)->orderBy('a.id', 'DESC')->setMaxResults($limit + 1);
        if (null !== $before) {
            $query->andWhere('a.id < :before')->setParameter('before', $before);
        }
        $rows = $query->fetchAllAssociative();
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $entries = array_map(static fn ($row) => [
            'id' => (int) $row['id'], 'transactionId' => $row['transaction_id'], 'requestId' => $row['request_id'],
            'context' => $row['context'], 'table' => $row['table_name'], 'recordId' => $row['record_id'], 'kind' => $row['kind'],
            'action' => $row['operation'], 'versionBefore' => null === $row['version_before'] ? null : (int) $row['version_before'],
            'version' => null === $row['version_after'] ? null : (int) $row['version_after'], 'actorId' => $row['actor_id'],
            'actorName' => $row['actor_name'], 'source' => $row['source'], 'occurredAt' => $row['occurred_at'], 'changes' => json_decode($row['changes'], true, flags: JSON_THROW_ON_ERROR),
            'schemaVersion' => (int) $row['schema_version'], 'redactionVersion' => (int) $row['redaction_version'],
        ], $rows);

        return ['entries' => $entries, 'nextBefore' => $more ? end($entries)['id'] : null];
    }
}
