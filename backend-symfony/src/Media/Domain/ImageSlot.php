<?php

declare(strict_types=1);

namespace App\Media\Domain;

use App\Shared\Domain\Revision;
use App\Shared\Domain\StaleRevisionException;
use DateTimeImmutable;

/**
 * The current picture of one owner and kind, with the version callers must quote to change it. A slot that was never
 * used is version 0 with no file; clearing keeps the slot with a higher version, so a stale request can never bring
 * an old picture back.
 */
final readonly class ImageSlot
{
    private function __construct(public SlotKey $key, public ?MediaFile $file, public Revision $revision)
    {
    }

    /** A slot that has never held a picture (version 0). */
    public static function start(SlotKey $key, DateTimeImmutable $now): self
    {
        return new self($key, null, Revision::start($now));
    }

    public static function reconstitute(SlotKey $key, ?MediaFile $file, Revision $revision): self
    {
        if (null !== $file && !$file->key->equals($key)) {
            throw MediaException::invalid('The stored image belongs to another slot.');
        }

        return new self($key, $file, $revision);
    }

    public function version(): int
    {
        return $this->revision->version;
    }

    /** @throws StaleRevisionException when the caller did not see the current version */
    public function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion < 0 || $expectedVersion !== $this->revision->version) {
            throw new StaleRevisionException();
        }
    }

    /** @throws StaleRevisionException */
    public function replace(MediaFile $file, int $expectedVersion, DateTimeImmutable $now): self
    {
        if (!$file->key->equals($this->key)) {
            throw MediaException::invalid('The image does not belong to this slot.');
        }

        return new self($this->key, $file, $this->revision->next($expectedVersion, $now));
    }

    /** @throws StaleRevisionException */
    public function clear(int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self($this->key, null, $this->revision->next($expectedVersion, $now));
    }
}
