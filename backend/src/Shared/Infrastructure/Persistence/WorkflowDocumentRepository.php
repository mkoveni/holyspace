<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use Doctrine\DBAL\Connection;
use DomainException;

abstract class WorkflowDocumentRepository
{
    public function __construct(
        protected readonly Connection $db,
        private readonly WorkflowDocumentMapper $mapper,
        private readonly string $table,
        private readonly WorkflowAuditJournal $audit,
    ) {
    }

    public function save(WorkflowRecord $record): void
    {
        $this->db->transactional(function () use ($record): void {
            $data = ['kind' => $this->mapper->kind($record), 'payload' => $this->mapper->payload($record, $record->version + 1), 'version' => $record->version + 1];
            if (0 === $record->version) {
                $this->db->insert($this->table, ['id' => $record->id] + $data);
            } elseif (1 !== $this->db->update($this->table, $data, ['id' => $record->id, 'version' => $record->version])) {
                throw new DomainException('This record changed. Reload it before trying again.');
            }
            $this->audit->append(substr($this->table, 0, -8), $data['kind'], $record);
        });
    }

    public function find(string $kind, string $id): ?WorkflowRecord
    {
        Input::id($id);
        $row = $this->db->fetchAssociative('SELECT * FROM '.$this->table.' WHERE id = ? AND kind = ?', [$id, $kind]);

        return false === $row ? null : $this->mapper->hydrate($kind, $row['payload']);
    }

    public function all(string $kind): array
    {
        return array_map(fn ($row) => $this->mapper->hydrate($kind, $row['payload']), $this->db->fetchAllAssociative('SELECT payload FROM '.$this->table.' WHERE kind = ? ORDER BY id', [$kind]));
    }
}
