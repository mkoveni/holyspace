<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Contract\ImageView;
use App\Media\Application\Contract\ImageViewReader;
use App\Media\Application\Contract\StoredImage;
use App\Media\Domain\ImageSlot;
use App\Media\Domain\MediaFile;
use App\Media\Domain\Repository\ImageSlotRepository;
use App\Media\Domain\SlotKey;
use DateTimeImmutable;

/** Repository and reader in one, as the real adapter will be; `saves` counts writes. */
final class InMemoryImageSlots implements ImageSlotRepository, ImageViewReader
{
    /** @var array<string, ImageSlot> */
    private array $slots = [];
    public int $saves = 0;
    public int $locks = 0;

    public function lock(SlotKey $key, DateTimeImmutable $now): ImageSlot
    {
        ++$this->locks;

        return $this->slots[self::id($key)] ?? ImageSlot::start($key, $now);
    }

    public function save(ImageSlot $slot): void
    {
        ++$this->saves;
        $this->slots[self::id($slot->key)] = $slot;
    }

    public function seed(ImageSlot $slot): void
    {
        $this->slots[self::id($slot->key)] = $slot;
    }

    public function current(SlotKey $key): ?ImageSlot
    {
        return $this->slots[self::id($key)] ?? null;
    }

    public function view(SlotKey $key): ImageView
    {
        $slot = $this->slots[self::id($key)] ?? null;

        return new ImageView($slot?->version() ?? 0, null === $slot?->file ? null : self::stored($slot->file));
    }

    public function file(string $fileId): ?StoredImage
    {
        foreach ($this->slots as $slot) {
            if ($slot->file?->id === $fileId) {
                return self::stored($slot->file);
            }
        }

        return null;
    }

    private static function stored(MediaFile $file): StoredImage
    {
        return new StoredImage($file->id, $file->key->kind, $file->path, MediaFile::MIME, $file->width, $file->height, $file->byteSize, $file->createdAt);
    }

    private static function id(SlotKey $key): string
    {
        return $key->ownerType->value.'/'.$key->ownerId.'/'.$key->kind->value;
    }
}
