<?php

declare(strict_types=1);

namespace App\Tests\Media\Domain;

use App\Media\Domain\ImageKind;
use App\Media\Domain\MediaException;
use App\Media\Domain\MediaFile;
use App\Media\Domain\SlotKey;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MediaFileTest extends TestCase
{
    private const string ID = 'abcdef01-2345-4678-89ab-cdef01234567';
    private const string OWNER = 'e0000000-0000-4000-8000-000000000001';

    public function testThePathIsShardedFromTheIdAndKindAlone(): void
    {
        $file = $this->file();

        self::assertSame('event_banner/ab/cd/'.self::ID.'.webp', $file->path);
        self::assertSame('event_banner/ab/cd/'.self::ID.'@640.webp', $file->variantPath(640));
        self::assertSame($file->variantPath(64), MediaFile::variantOf($file->path, 64));
    }

    public function testAnIdThatCouldEscapeTheStorageRootIsRefused(): void
    {
        foreach (['../../etc/passwd', 'ABCDEF01-2345-4678-89AB-CDEF01234567', '', self::ID.'/x'] as $id) {
            try {
                $this->file($id);
                self::fail('Expected '.$id.' to be refused.');
            } catch (MediaException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRefusesBadSizesAndChecksums(): void
    {
        foreach ([[0, 10, 1, 'a'], [10, 8001, 1, 'a'], [10, 10, 0, 'a'], [10, 10, MediaFile::MAX_BYTES + 1, 'a'], [10, 10, 5, 'zz']] as [$w, $h, $bytes, $sum]) {
            try {
                $this->file(self::ID, $w, $h, $bytes, 'a' === $sum ? str_repeat('a', 64) : $sum);
                self::fail('Expected '.json_encode([$w, $h, $bytes, $sum]).' to be refused.');
            } catch (MediaException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function file(string $id = self::ID, int $width = 1280, int $height = 720, int $bytes = 1000, string $sha = ''): MediaFile
    {
        return MediaFile::create(SlotKey::of(ImageKind::EventBanner, self::OWNER), $id, $width, $height, $bytes, '' === $sha ? str_repeat('b', 64) : $sha, new DateTimeImmutable('2026-11-09'));
    }
}
