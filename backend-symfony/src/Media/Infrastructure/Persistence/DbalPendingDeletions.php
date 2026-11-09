<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence;

use App\Media\Application\Port\DeletionQueue;
use App\Media\Application\Port\PendingDeletions;
use App\Media\Application\Port\PendingFile;
use App\Media\Domain\MediaFile;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalPendingDeletions implements PendingDeletions, DeletionQueue
{
    public function __construct(private Connection $connection)
    {
    }

    public function queue(MediaFile $file, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO media_pending_deletions (file_id, path, queued_at) VALUES (?, ?, ?) ON CONFLICT (file_id) DO NOTHING',
            [$file->id, $file->path, Timestamps::toDatabase($at)],
        );
    }

    public function next(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT file_id, path FROM media_pending_deletions ORDER BY queued_at, file_id LIMIT '.max(1, $limit));

        return array_map(static fn (array $row): PendingFile => new PendingFile((string) $row['file_id'], (string) $row['path']), $rows);
    }

    public function finish(string $fileId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($fileId): void {
            $connection->executeStatement('DELETE FROM media_pending_deletions WHERE file_id = ?', [$fileId]);
            $connection->executeStatement('DELETE FROM media_files f WHERE f.id = ? AND NOT EXISTS (SELECT 1 FROM media_slots s WHERE s.file_id = f.id)', [$fileId]);
        });
    }
}
