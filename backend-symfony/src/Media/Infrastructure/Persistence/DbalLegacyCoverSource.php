<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence;

use App\Media\Application\Port\LegacyCover;
use App\Media\Application\Port\LegacyCoverSource;
use App\Media\Domain\ImageKind;
use Doctrine\DBAL\Connection;

/**
 * TEMPORARY: reads the V37 cover tables, which belong to no Media table set, for the one-off import. Deleted with
 * those tables (see `docs/porting/media.md`, M6 runbook).
 */
final readonly class DbalLegacyCoverSource implements LegacyCoverSource
{
    public function __construct(private Connection $connection)
    {
    }

    public function covers(): array
    {
        $covers = [];
        foreach ([['event_cover_images', 'event_id', ImageKind::Event], ['community_cover_images', 'community_id', ImageKind::Community]] as [$table, $column, $kind]) {
            $rows = $this->connection->fetchAllAssociative(sprintf('SELECT %s AS owner_id, image_id FROM %s WHERE image_id IS NOT NULL ORDER BY %s', $column, $table, $column));
            foreach ($rows as $row) {
                $covers[] = new LegacyCover($kind, (string) $row['owner_id'], (string) $row['image_id']);
            }
        }

        return $covers;
    }

    public function bytes(string $imageId): ?string
    {
        $content = $this->connection->fetchOne('SELECT content FROM cover_images WHERE id = ?', [$imageId]);
        if (false === $content) {
            return null;
        }

        return \is_resource($content) ? (string) stream_get_contents($content) : (string) $content;
    }
}
