<?php

declare(strict_types=1);

namespace App\Tests\Media\Domain;

use App\Media\Domain\ImageKind;
use App\Media\Domain\ImageSlot;
use App\Media\Domain\MediaException;
use App\Media\Domain\MediaFile;
use App\Media\Domain\SlotKey;
use App\Shared\Domain\Revision;
use App\Shared\Domain\StaleRevisionException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ImageSlotTest extends TestCase
{
    private const string OWNER = 'e0000000-0000-4000-8000-000000000001';
    private const string FILE = 'abcdef01-2345-4678-89ab-cdef01234567';

    public function testANewSlotIsVersionZeroWithNoFile(): void
    {
        $slot = ImageSlot::start($this->key(), $this->at('2026-11-09'));

        self::assertSame(0, $slot->version());
        self::assertNull($slot->file);
    }

    public function testReplacingAdvancesTheVersionAndHoldsTheNewFile(): void
    {
        $slot = ImageSlot::start($this->key(), $this->at('2026-11-09'));
        $file = $this->file();

        $next = $slot->replace($file, 0, $this->at('2026-11-10'));

        self::assertSame(1, $next->version());
        self::assertSame($file, $next->file);
        self::assertSame(0, $slot->version(), 'the original is unchanged');
        self::assertEquals($this->at('2026-11-10'), $next->revision->timeline->updatedAt);
    }

    public function testClearingKeepsAHigherVersion(): void
    {
        $slot = ImageSlot::start($this->key(), $this->at('2026-11-09'))->replace($this->file(), 0, $this->at('2026-11-10'));

        $cleared = $slot->clear(1, $this->at('2026-11-11'));

        self::assertNull($cleared->file);
        self::assertSame(2, $cleared->version());
        $this->expectException(StaleRevisionException::class);
        $cleared->replace($this->file(), 1, $this->at('2026-11-12'));
    }

    public function testAStaleVersionIsRefusedByEveryChange(): void
    {
        $slot = ImageSlot::start($this->key(), $this->at('2026-11-09'))->replace($this->file(), 0, $this->at('2026-11-10'));

        foreach ([fn () => $slot->replace($this->file(), 0, $this->at('2026-11-11')), fn () => $slot->clear(0, $this->at('2026-11-11')), fn () => $slot->assertVersion(5), fn () => $slot->assertVersion(-1)] as $change) {
            try {
                $change();
                self::fail('Expected a stale version.');
            } catch (StaleRevisionException) {
                self::addToAssertionCount(1);
            }
        }
        $slot->assertVersion(1);
    }

    public function testAFileOfAnotherSlotIsRefused(): void
    {
        $slot = ImageSlot::start($this->key(), $this->at('2026-11-09'));
        $other = MediaFile::create(SlotKey::of(ImageKind::Event, self::OWNER), self::FILE, 1280, 720, 10, str_repeat('b', 64), $this->at('2026-11-09'));

        $this->expectException(MediaException::class);
        $slot->replace($other, 0, $this->at('2026-11-10'));
    }

    public function testReconstitutingWithAFileOfAnotherSlotIsRefused(): void
    {
        $other = MediaFile::create(SlotKey::of(ImageKind::Event, self::OWNER), self::FILE, 1280, 720, 10, str_repeat('b', 64), $this->at('2026-11-09'));

        $this->expectException(MediaException::class);
        ImageSlot::reconstitute($this->key(), $other, Revision::start($this->at('2026-11-09')));
    }

    private function key(): SlotKey
    {
        return SlotKey::of(ImageKind::EventBanner, self::OWNER);
    }

    private function file(): MediaFile
    {
        return MediaFile::create($this->key(), self::FILE, 1280, 720, 10, str_repeat('b', 64), $this->at('2026-11-09'));
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }
}
