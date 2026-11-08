<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityCoverReader;
use App\Communities\Domain\Repository\CommunityCoverRepository;
use App\Shared\Domain\CoverSlot;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalCommunityCoverStore implements CommunityCoverReader, CommunityCoverRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function cover(string $communityId): CoverSlot
    {
        $row = $this->connection->fetchAssociative('SELECT image_id, version FROM community_cover_images WHERE community_id = ?', [$communityId]);

        return false === $row ? CoverSlot::empty() : new CoverSlot(null === $row['image_id'] ? null : (string) $row['image_id'], (int) $row['version']);
    }

    public function save(string $communityId, CoverSlot $slot, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO community_cover_images (community_id, image_id, version, updated_at) VALUES (:community, :image, :version, :at)
             ON CONFLICT (community_id) DO UPDATE SET image_id = EXCLUDED.image_id, version = EXCLUDED.version, updated_at = EXCLUDED.updated_at',
            ['community' => $communityId, 'image' => $slot->imageId, 'version' => $slot->version, 'at' => Timestamps::toDatabase($at)],
        );
    }
}
