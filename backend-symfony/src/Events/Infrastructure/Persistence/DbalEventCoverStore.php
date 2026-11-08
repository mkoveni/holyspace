<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\EventCoverReader;
use App\Events\Domain\Repository\EventCoverRepository;
use App\Shared\Domain\CoverSlot;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalEventCoverStore implements EventCoverReader, EventCoverRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function cover(string $eventId): CoverSlot
    {
        $row = $this->connection->fetchAssociative('SELECT image_id, version FROM event_cover_images WHERE event_id = ?', [$eventId]);

        return false === $row ? CoverSlot::empty() : new CoverSlot(null === $row['image_id'] ? null : (string) $row['image_id'], (int) $row['version']);
    }

    public function save(string $eventId, CoverSlot $slot, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_cover_images (event_id, image_id, version, updated_at) VALUES (:event, :image, :version, :at)
             ON CONFLICT (event_id) DO UPDATE SET image_id = EXCLUDED.image_id, version = EXCLUDED.version, updated_at = EXCLUDED.updated_at',
            ['event' => $eventId, 'image' => $slot->imageId, 'version' => $slot->version, 'at' => Timestamps::toDatabase($at)],
        );
    }
}
