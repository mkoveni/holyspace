<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence;

use App\Media\Application\Contract\ImageView;
use App\Media\Application\Contract\ImageViewReader;
use App\Media\Application\Contract\StoredImage;
use App\Media\Domain\ImageKind;
use App\Media\Domain\ImageSlot;
use App\Media\Domain\MediaFile;
use App\Media\Domain\Repository\ImageSlotRepository;
use App\Media\Domain\SlotKey;
use App\Shared\Domain\Revision;
use App\Shared\Domain\StaleRevisionException;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Slots and the files they point at. A slot is serialized by an advisory lock on its key, which also covers a slot that
 * has no row yet; the write then checks the version it advanced from, so a lost race is a 409 and never an overwrite.
 */
final readonly class DbalImageSlotStore implements ImageSlotRepository, ImageViewReader
{
    private const string FILE_COLUMNS = 'f.id AS file_id, f.owner_type, f.owner_id, f.kind, f.path, f.width, f.height, f.byte_size, f.sha256, f.created_at AS file_created_at';

    public function __construct(private Connection $connection, private AdvisoryLock $locks)
    {
    }

    public function lock(SlotKey $key, DateTimeImmutable $now): ImageSlot
    {
        $this->locks->lockKey('media:'.$key->ownerType->value.':'.$key->ownerId.':'.$key->kind->value);

        return $this->load($key) ?? ImageSlot::start($key, $now);
    }

    public function save(ImageSlot $slot): void
    {
        if (null !== $slot->file) {
            $this->connection->executeStatement(
                'INSERT INTO media_files (id, owner_type, owner_id, kind, path, mime, width, height, byte_size, sha256, created_at)
                 VALUES (:id, :owner_type, :owner_id, :kind, :path, :mime, :width, :height, :bytes, :sha, :at) ON CONFLICT (id) DO NOTHING',
                [
                    'id' => $slot->file->id,
                    'owner_type' => $slot->key->ownerType->value,
                    'owner_id' => $slot->key->ownerId,
                    'kind' => $slot->key->kind->value,
                    'path' => $slot->file->path,
                    'mime' => MediaFile::MIME,
                    'width' => $slot->file->width,
                    'height' => $slot->file->height,
                    'bytes' => $slot->file->byteSize,
                    'sha' => $slot->file->sha256,
                    'at' => Timestamps::toDatabase($slot->file->createdAt),
                ],
            );
        }
        $changed = $this->connection->executeStatement(
            'INSERT INTO media_slots (owner_type, owner_id, kind, file_id, version, updated_at)
             VALUES (:owner_type, :owner_id, :kind, :file, :version, :at)
             ON CONFLICT (owner_type, owner_id, kind) DO UPDATE
                SET file_id = EXCLUDED.file_id, version = EXCLUDED.version, updated_at = EXCLUDED.updated_at
                WHERE media_slots.version = :previous',
            [
                'owner_type' => $slot->key->ownerType->value,
                'owner_id' => $slot->key->ownerId,
                'kind' => $slot->key->kind->value,
                'file' => $slot->file?->id,
                'version' => $slot->version(),
                'at' => Timestamps::toDatabase($slot->revision->timeline->updatedAt),
                'previous' => $slot->revision->previousVersion(),
            ],
        );
        if (1 !== $changed) {
            throw new StaleRevisionException();
        }
    }

    public function view(SlotKey $key): ImageView
    {
        $row = $this->row($key);

        return false === $row ? new ImageView(0, null) : new ImageView((int) $row['version'], null === $row['file_id'] ? null : self::stored($row));
    }

    public function file(string $fileId): ?StoredImage
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::FILE_COLUMNS.' FROM media_files f WHERE f.id = ?', [$fileId]);

        return false === $row ? null : self::stored($row);
    }

    private function load(SlotKey $key): ?ImageSlot
    {
        $row = $this->row($key);
        if (false === $row) {
            return null;
        }
        $updated = Timestamps::fromDatabase((string) $row['updated_at']);

        return ImageSlot::reconstitute($key, null === $row['file_id'] ? null : self::mediaFile($row, $key), Revision::reconstitute((int) $row['version'], $updated, $updated));
    }

    /** @return array<string, mixed>|false */
    private function row(SlotKey $key): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT s.version, s.updated_at, '.self::FILE_COLUMNS.'
             FROM media_slots s LEFT JOIN media_files f ON f.id = s.file_id
             WHERE s.owner_type = ? AND s.owner_id = ? AND s.kind = ?',
            [$key->ownerType->value, $key->ownerId, $key->kind->value],
        );
    }

    /** @param array<string, mixed> $row */
    private static function mediaFile(array $row, SlotKey $key): MediaFile
    {
        return MediaFile::reconstitute((string) $row['file_id'], $key, (string) $row['path'], (int) $row['width'], (int) $row['height'], (int) $row['byte_size'], (string) $row['sha256'], Timestamps::fromDatabase((string) $row['file_created_at']));
    }

    /** @param array<string, mixed> $row */
    private static function stored(array $row): StoredImage
    {
        return new StoredImage(
            (string) $row['file_id'],
            ImageKind::from((string) $row['kind']),
            (string) $row['path'],
            MediaFile::MIME,
            (int) $row['width'],
            (int) $row['height'],
            (int) $row['byte_size'],
            Timestamps::fromDatabase((string) $row['file_created_at']),
        );
    }
}
