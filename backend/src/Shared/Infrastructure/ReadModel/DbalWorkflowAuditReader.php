<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ReadModel;

use App\Shared\Application\DTO\WorkflowAuditEntry;
use App\Shared\Application\DTO\WorkflowAuditPage;
use App\Shared\Application\ReadModel\WorkflowAuditReader;
use App\Shared\Domain\ValueObject\Input;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(WorkflowAuditReader::class)]
final readonly class DbalWorkflowAuditReader implements WorkflowAuditReader
{
    public function __construct(private Connection $db)
    {
    }

    public function page(string $context, string $recordId, int $limit = 25, ?int $before = null): WorkflowAuditPage
    {
        $contexts = ['payments', 'service_planning', 'forms', 'volunteering', 'children', 'giving', 'attendance', 'ministries', 'communications'];
        if (!in_array($context, $contexts, true)) {
            throw new OutOfBoundsException('Context not found.');
        }
        Input::id($recordId);
        if ($limit < 1 || $limit > 100 || (null !== $before && $before < 1)) {
            throw new InvalidArgumentException('Invalid audit pagination.');
        }
        $storage = match ($context) {
            'children' => 'childrenministry', 'service_planning' => 'serviceplanning', default => $context,
        };
        if (false === $this->db->fetchOne('SELECT 1 FROM '.$storage.'_records WHERE id = ?', [$recordId])) {
            throw new OutOfBoundsException('Record not found.');
        }
        $query = $this->db->createQueryBuilder()->select('a.*, u.username AS actor_name')->from('workflow_audit', 'a')->leftJoin('a', 'user_accounts', 'u', 'a.actor_id = u.id')->where('a.context = :context')->andWhere('a.record_id = :record')->setParameter('context', $storage)->setParameter('record', $recordId)->orderBy('a.version', 'DESC')->setMaxResults($limit + 1);
        if (null !== $before) {
            $query->andWhere('a.version < :before')->setParameter('before', $before);
        }
        $rows = $query->fetchAllAssociative();
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $entries = array_map(fn ($r) => new WorkflowAuditEntry($r['id'], $r['record_id'], $r['kind'], $r['action'], (int) $r['version'], $r['actor_id'], $r['actor_name'], $r['occurred_at']), $rows);

        return new WorkflowAuditPage($entries, $hasMore ? end($entries)->version : null);
    }
}
