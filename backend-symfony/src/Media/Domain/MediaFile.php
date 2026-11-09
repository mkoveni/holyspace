<?php

declare(strict_types=1);

namespace App\Media\Domain;

use DateTimeImmutable;

/**
 * One stored picture. Its id is random and new for every upload, so a file never changes after it is written; its
 * path is built only from the kind and that id, never from anything the client sent.
 */
final readonly class MediaFile
{
    public const string UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';
    public const string MIME = 'image/webp';
    public const int MAX_BYTES = 5 * 1024 * 1024;

    private function __construct(
        public string $id,
        public SlotKey $key,
        public string $path,
        public int $width,
        public int $height,
        public int $byteSize,
        public string $sha256,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(SlotKey $key, string $id, int $width, int $height, int $byteSize, string $sha256, DateTimeImmutable $now): self
    {
        if (1 !== preg_match(self::UUID, $id)) {
            throw MediaException::invalid('The stored image id is not valid.');
        }

        return self::reconstitute($id, $key, self::pathFor($key->kind, $id), $width, $height, $byteSize, $sha256, $now);
    }

    public static function reconstitute(string $id, SlotKey $key, string $path, int $width, int $height, int $byteSize, string $sha256, DateTimeImmutable $createdAt): self
    {
        if ($width < 1 || $width > ImagePolicy::MAX_SOURCE_SIDE || $height < 1 || $height > ImagePolicy::MAX_SOURCE_SIDE) {
            throw MediaException::invalid('The stored image size is not valid.');
        }
        if ($byteSize < 1 || $byteSize > self::MAX_BYTES) {
            throw MediaException::invalid('The processed image is larger than 5 MB.');
        }
        if (1 !== preg_match('/\A[0-9a-f]{64}\z/', $sha256)) {
            throw MediaException::invalid('The stored image checksum is not valid.');
        }

        return new self($id, $key, $path, $width, $height, $byteSize, $sha256, $createdAt);
    }

    /** `{kind}/{aa}/{bb}/{id}.webp`, sharded by the first two pairs of the id so no directory grows large. */
    public static function pathFor(ImageKind $kind, string $id): string
    {
        return sprintf('%s/%s/%s/%s.webp', $kind->value, substr($id, 0, 2), substr($id, 2, 2), $id);
    }

    /** The smaller copy of this file at the given width, written beside it. */
    public function variantPath(int $width): string
    {
        return self::variantOf($this->path, $width);
    }

    /** The same rule for a stored path read back from the database. */
    public static function variantOf(string $path, int $width): string
    {
        return substr($path, 0, -strlen('.webp')).'@'.$width.'.webp';
    }
}
